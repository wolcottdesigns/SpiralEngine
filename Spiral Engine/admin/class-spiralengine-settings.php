<?php
// includes/admin/class-spiralengine-settings.php

/**
 * Spiral Engine Settings Interface
 * 
 * Implements comprehensive settings interface with all tabs from master plan,
 * settings API integration, validation, and import/export functionality.
 *
 * @package    Spiral_Engine
 * @subpackage Spiral_Engine/admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPIRALENGINE_Settings {
    
    /**
     * Settings tabs
     */
    private $tabs = array();
    
    /**
     * Current tab
     */
    private $current_tab;
    
    /**
     * Time manager instance
     */
    private $time_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->init_tabs();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_settings_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_spiralengine_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_spiralengine_export_settings', array($this, 'ajax_export_settings'));
        add_action('wp_ajax_spiralengine_import_settings', array($this, 'ajax_import_settings'));
        add_action('wp_ajax_spiralengine_test_api_connection', array($this, 'ajax_test_api_connection'));
        
        // Settings link on plugins page
        add_filter('plugin_action_links_' . SPIRALENGINE_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }
    
    /**
     * Initialize tabs
     */
    private function init_tabs() {
        $this->tabs = array(
            'general' => array(
                'title' => __('General Settings', 'spiralengine'),
                'icon' => 'dashicons-admin-generic',
                'callback' => array($this, 'render_general_tab')
            ),
            'user' => array(
                'title' => __('User Settings', 'spiralengine'),
                'icon' => 'dashicons-admin-users',
                'callback' => array($this, 'render_user_tab')
            ),
            'permissions' => array(
                'title' => __('Permissions', 'spiralengine'),
                'icon' => 'dashicons-lock',
                'callback' => array($this, 'render_permissions_tab')
            ),
            'api' => array(
                'title' => __('API Configuration', 'spiralengine'),
                'icon' => 'dashicons-admin-plugins',
                'callback' => array($this, 'render_api_tab')
            ),
            'performance' => array(
                'title' => __('Performance', 'spiralengine'),
                'icon' => 'dashicons-performance',
                'callback' => array($this, 'render_performance_tab')
            ),
            'security' => array(
                'title' => __('Security', 'spiralengine'),
                'icon' => 'dashicons-shield',
                'callback' => array($this, 'render_security_tab')
            ),
            'backup' => array(
                'title' => __('Backup & Restore', 'spiralengine'),
                'icon' => 'dashicons-backup',
                'callback' => array($this, 'render_backup_tab')
            ),
            'maintenance' => array(
                'title' => __('Maintenance', 'spiralengine'),
                'icon' => 'dashicons-admin-tools',
                'callback' => array($this, 'render_maintenance_tab')
            ),
            'advanced' => array(
                'title' => __('Advanced', 'spiralengine'),
                'icon' => 'dashicons-admin-settings',
                'callback' => array($this, 'render_advanced_tab')
            )
        );
        
        // Allow filtering of tabs
        $this->tabs = apply_filters('spiralengine_settings_tabs', $this->tabs);
    }
    
    /**
     * Add settings menu
     */
    public function add_settings_menu() {
        add_submenu_page(
            'spiralengine',
            __('SPIRAL Engine Settings', 'spiralengine'),
            __('Settings', 'spiralengine'),
            'manage_options',
            'spiralengine-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting('spiralengine_general', 'spiralengine_general_settings', array($this, 'sanitize_general_settings'));
        
        // User settings
        register_setting('spiralengine_user', 'spiralengine_user_settings', array($this, 'sanitize_user_settings'));
        
        // Permission settings
        register_setting('spiralengine_permissions', 'spiralengine_permission_settings', array($this, 'sanitize_permission_settings'));
        
        // API settings
        register_setting('spiralengine_api', 'spiralengine_api_settings', array($this, 'sanitize_api_settings'));
        
        // Performance settings
        register_setting('spiralengine_performance', 'spiralengine_performance_settings', array($this, 'sanitize_performance_settings'));
        
        // Security settings
        register_setting('spiralengine_security', 'spiralengine_security_settings', array($this, 'sanitize_security_settings'));
        
        // Backup settings
        register_setting('spiralengine_backup', 'spiralengine_backup_settings', array($this, 'sanitize_backup_settings'));
        
        // Maintenance settings
        register_setting('spiralengine_maintenance', 'spiralengine_maintenance_settings', array($this, 'sanitize_maintenance_settings'));
        
        // Advanced settings
        register_setting('spiralengine_advanced', 'spiralengine_advanced_settings', array($this, 'sanitize_advanced_settings'));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $this->current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        
        if (!isset($this->tabs[$this->current_tab])) {
            $this->current_tab = 'general';
        }
        ?>
        <div class="wrap spiralengine-settings-wrap">
            <h1>
                <span class="dashicons dashicons-admin-generic"></span>
                <?php echo esc_html__('SPIRAL Engine Settings', 'spiralengine'); ?>
            </h1>
            
            <?php $this->render_tabs(); ?>
            
            <div class="spiralengine-settings-content">
                <form method="post" action="options.php" id="spiralengine-settings-form">
                    <?php
                    // Call tab callback
                    if (isset($this->tabs[$this->current_tab]['callback'])) {
                        call_user_func($this->tabs[$this->current_tab]['callback']);
                    }
                    ?>
                    
                    <div class="spiralengine-settings-actions">
                        <?php submit_button(__('Save Settings', 'spiralengine'), 'primary', 'submit', false); ?>
                        <button type="button" class="button button-secondary" id="spiralengine-reset-settings">
                            <?php esc_html_e('Reset to Defaults', 'spiralengine'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="spiralengine-export-settings">
                            <?php esc_html_e('Export Settings', 'spiralengine'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="spiralengine-import-settings">
                            <?php esc_html_e('Import Settings', 'spiralengine'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tabs
     */
    private function render_tabs() {
        ?>
        <nav class="nav-tab-wrapper spiralengine-nav-tab-wrapper">
            <?php foreach ($this->tabs as $tab_id => $tab) : ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $tab_id)); ?>" 
                   class="nav-tab <?php echo $this->current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                    <span class="<?php echo esc_attr($tab['icon']); ?>"></span>
                    <?php echo esc_html($tab['title']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }
    
    /**
     * Render General tab
     */
    public function render_general_tab() {
        settings_fields('spiralengine_general');
        $settings = get_option('spiralengine_general_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="spiralengine_site_name"><?php esc_html_e('Site Name', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="spiralengine_site_name" 
                           name="spiralengine_general_settings[site_name]" 
                           value="<?php echo esc_attr($settings['site_name'] ?? get_bloginfo('name')); ?>" 
                           class="regular-text" />
                    <p class="description"><?php esc_html_e('Display name for your SPIRAL Engine installation', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_timezone"><?php esc_html_e('Timezone', 'spiralengine'); ?></label>
                </th>
                <td>
                    <?php
                    $current_timezone = $settings['timezone'] ?? wp_timezone_string();
                    $tzstring = $current_timezone;
                    
                    $check_zone_info = true;
                    if (false !== strpos($tzstring, 'Etc/GMT')) {
                        $tzstring = '';
                    }
                    
                    if (empty($tzstring)) {
                        $check_zone_info = false;
                    }
                    ?>
                    <select id="spiralengine_timezone" name="spiralengine_general_settings[timezone]">
                        <?php echo wp_timezone_choice($tzstring, get_user_locale()); ?>
                    </select>
                    <p class="description"><?php esc_html_e('Choose your timezone for accurate time tracking', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_date_format"><?php esc_html_e('Date Format', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <?php
                        $date_formats = array(
                            'Y-m-d',
                            'm/d/Y',
                            'd/m/Y',
                            'F j, Y'
                        );
                        
                        $custom = true;
                        $current_format = $settings['date_format'] ?? get_option('date_format');
                        
                        foreach ($date_formats as $format) {
                            echo '<label>';
                            echo '<input type="radio" name="spiralengine_general_settings[date_format]" value="' . esc_attr($format) . '"';
                            
                            if ($current_format === $format) {
                                echo ' checked="checked"';
                                $custom = false;
                            }
                            
                            echo ' /> ' . date_i18n($format) . '</label><br />';
                        }
                        
                        echo '<label>';
                        echo '<input type="radio" name="spiralengine_general_settings[date_format]" value="custom"';
                        checked($custom);
                        echo '/> ' . __('Custom:', 'spiralengine') . ' </label>';
                        echo '<input type="text" name="spiralengine_general_settings[date_format_custom]" value="' . esc_attr($current_format) . '" class="small-text" />';
                        ?>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_time_format"><?php esc_html_e('Time Format', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <?php
                        $time_formats = array(
                            'g:i a',
                            'g:i A',
                            'H:i'
                        );
                        
                        $custom = true;
                        $current_format = $settings['time_format'] ?? get_option('time_format');
                        
                        foreach ($time_formats as $format) {
                            echo '<label>';
                            echo '<input type="radio" name="spiralengine_general_settings[time_format]" value="' . esc_attr($format) . '"';
                            
                            if ($current_format === $format) {
                                echo ' checked="checked"';
                                $custom = false;
                            }
                            
                            echo ' /> ' . date_i18n($format) . '</label><br />';
                        }
                        
                        echo '<label>';
                        echo '<input type="radio" name="spiralengine_general_settings[time_format]" value="custom"';
                        checked($custom);
                        echo '/> ' . __('Custom:', 'spiralengine') . ' </label>';
                        echo '<input type="text" name="spiralengine_general_settings[time_format_custom]" value="' . esc_attr($current_format) . '" class="small-text" />';
                        ?>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_enable_features"><?php esc_html_e('Enable Features', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_general_settings[enable_assessments]" 
                                   value="1" 
                                   <?php checked($settings['enable_assessments'] ?? true); ?> />
                            <?php esc_html_e('Assessments', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_general_settings[enable_episodes]" 
                                   value="1" 
                                   <?php checked($settings['enable_episodes'] ?? true); ?> />
                            <?php esc_html_e('Episode Tracking', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_general_settings[enable_ai]" 
                                   value="1" 
                                   <?php checked($settings['enable_ai'] ?? true); ?> />
                            <?php esc_html_e('AI Features', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_general_settings[enable_analytics]" 
                                   value="1" 
                                   <?php checked($settings['enable_analytics'] ?? true); ?> />
                            <?php esc_html_e('Analytics', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_general_settings[enable_notifications]" 
                                   value="1" 
                                   <?php checked($settings['enable_notifications'] ?? true); ?> />
                            <?php esc_html_e('Notifications', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_default_language"><?php esc_html_e('Default Language', 'spiralengine'); ?></label>
                </th>
                <td>
                    <select id="spiralengine_default_language" name="spiralengine_general_settings[default_language]">
                        <?php
                        $languages = array(
                            'en_US' => 'English (US)',
                            'en_GB' => 'English (UK)',
                            'es_ES' => 'Español',
                            'fr_FR' => 'Français',
                            'de_DE' => 'Deutsch',
                            'it_IT' => 'Italiano',
                            'pt_BR' => 'Português (Brasil)',
                            'ja' => '日本語',
                            'zh_CN' => '中文 (简体)',
                            'ko_KR' => '한국어'
                        );
                        
                        $current_language = $settings['default_language'] ?? get_locale();
                        
                        foreach ($languages as $code => $name) {
                            echo '<option value="' . esc_attr($code) . '"' . selected($current_language, $code, false) . '>';
                            echo esc_html($name);
                            echo '</option>';
                        }
                        ?>
                    </select>
                    <p class="description"><?php esc_html_e('Default language for SPIRAL Engine interface', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render User tab
     */
    public function render_user_tab() {
        settings_fields('spiralengine_user');
        $settings = get_option('spiralengine_user_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="spiralengine_registration"><?php esc_html_e('User Registration', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="spiralengine_user_settings[allow_registration]" 
                               value="1" 
                               <?php checked($settings['allow_registration'] ?? true); ?> />
                        <?php esc_html_e('Allow new user registration', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_default_role"><?php esc_html_e('Default User Role', 'spiralengine'); ?></label>
                </th>
                <td>
                    <select id="spiralengine_default_role" name="spiralengine_user_settings[default_role]">
                        <?php
                        $roles = wp_roles()->get_names();
                        $current_role = $settings['default_role'] ?? 'subscriber';
                        
                        foreach ($roles as $role => $name) {
                            echo '<option value="' . esc_attr($role) . '"' . selected($current_role, $role, false) . '>';
                            echo esc_html(translate_user_role($name));
                            echo '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Profile Fields', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_user_settings[require_phone]" 
                                   value="1" 
                                   <?php checked($settings['require_phone'] ?? false); ?> />
                            <?php esc_html_e('Require phone number', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_user_settings[require_birthdate]" 
                                   value="1" 
                                   <?php checked($settings['require_birthdate'] ?? false); ?> />
                            <?php esc_html_e('Require birthdate', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_user_settings[require_timezone]" 
                                   value="1" 
                                   <?php checked($settings['require_timezone'] ?? true); ?> />
                            <?php esc_html_e('Require timezone selection', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_user_settings[enable_avatar]" 
                                   value="1" 
                                   <?php checked($settings['enable_avatar'] ?? true); ?> />
                            <?php esc_html_e('Enable custom avatars', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_session_timeout"><?php esc_html_e('Session Timeout', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="spiralengine_session_timeout" 
                           name="spiralengine_user_settings[session_timeout]" 
                           value="<?php echo esc_attr($settings['session_timeout'] ?? 30); ?>" 
                           min="5" 
                           max="1440" 
                           class="small-text" />
                    <?php esc_html_e('minutes', 'spiralengine'); ?>
                    <p class="description"><?php esc_html_e('Automatically log out inactive users after this time', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Privacy Settings', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_user_settings[anonymous_mode]" 
                                   value="1" 
                                   <?php checked($settings['anonymous_mode'] ?? false); ?> />
                            <?php esc_html_e('Allow anonymous mode', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_user_settings[data_export]" 
                                   value="1" 
                                   <?php checked($settings['data_export'] ?? true); ?> />
                            <?php esc_html_e('Allow users to export their data', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_user_settings[data_deletion]" 
                                   value="1" 
                                   <?php checked($settings['data_deletion'] ?? true); ?> />
                            <?php esc_html_e('Allow users to delete their data', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_data_retention"><?php esc_html_e('Data Retention', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="spiralengine_data_retention" 
                           name="spiralengine_user_settings[data_retention_days]" 
                           value="<?php echo esc_attr($settings['data_retention_days'] ?? 365); ?>" 
                           min="30" 
                           max="3650" 
                           class="small-text" />
                    <?php esc_html_e('days', 'spiralengine'); ?>
                    <p class="description"><?php esc_html_e('Automatically delete user data older than this', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Permissions tab
     */
    public function render_permissions_tab() {
        settings_fields('spiralengine_permissions');
        $settings = get_option('spiralengine_permission_settings', array());
        
        $capabilities = array(
            'spiralengine_view_dashboard' => __('View Dashboard', 'spiralengine'),
            'spiralengine_manage_assessments' => __('Manage Assessments', 'spiralengine'),
            'spiralengine_view_episodes' => __('View Episodes', 'spiralengine'),
            'spiralengine_manage_episodes' => __('Manage Episodes', 'spiralengine'),
            'spiralengine_use_ai' => __('Use AI Features', 'spiralengine'),
            'spiralengine_view_analytics' => __('View Analytics', 'spiralengine'),
            'spiralengine_export_data' => __('Export Data', 'spiralengine'),
            'spiralengine_manage_users' => __('Manage Users', 'spiralengine'),
            'spiralengine_manage_settings' => __('Manage Settings', 'spiralengine')
        );
        
        $roles = wp_roles()->get_names();
        ?>
        <div class="spiralengine-permissions-grid">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Capability', 'spiralengine'); ?></th>
                        <?php foreach ($roles as $role => $name) : ?>
                            <th><?php echo esc_html(translate_user_role($name)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($capabilities as $cap => $label) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($label); ?></strong></td>
                            <?php foreach ($roles as $role => $name) : ?>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               name="spiralengine_permission_settings[<?php echo esc_attr($role); ?>][<?php echo esc_attr($cap); ?>]" 
                                               value="1" 
                                               <?php checked(isset($settings[$role][$cap]) && $settings[$role][$cap]); ?> />
                                        <span class="screen-reader-text">
                                            <?php echo esc_html($label . ' - ' . translate_user_role($name)); ?>
                                        </span>
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <h3><?php esc_html_e('Custom Capabilities', 'spiralengine'); ?></h3>
        <p class="description"><?php esc_html_e('Add custom capabilities for fine-grained control', 'spiralengine'); ?></p>
        
        <div id="spiralengine-custom-capabilities">
            <?php
            $custom_caps = $settings['custom_capabilities'] ?? array();
            if (empty($custom_caps)) {
                $custom_caps = array('');
            }
            
            foreach ($custom_caps as $index => $cap) :
            ?>
                <div class="spiralengine-custom-cap-row">
                    <input type="text" 
                           name="spiralengine_permission_settings[custom_capabilities][]" 
                           value="<?php echo esc_attr($cap); ?>" 
                           placeholder="<?php esc_attr_e('capability_name', 'spiralengine'); ?>" 
                           class="regular-text" />
                    <button type="button" class="button spiralengine-remove-cap"><?php esc_html_e('Remove', 'spiralengine'); ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        
        <button type="button" class="button" id="spiralengine-add-custom-cap">
            <?php esc_html_e('Add Custom Capability', 'spiralengine'); ?>
        </button>
        <?php
    }
    
    /**
     * Render API tab
     */
    public function render_api_tab() {
        settings_fields('spiralengine_api');
        $settings = get_option('spiralengine_api_settings', array());
        ?>
        <h2><?php esc_html_e('OpenAI Configuration', 'spiralengine'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="spiralengine_openai_api_key"><?php esc_html_e('API Key', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="spiralengine_openai_api_key" 
                           name="spiralengine_api_settings[openai_api_key]" 
                           value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>" 
                           class="regular-text" />
                    <button type="button" class="button" id="spiralengine-test-openai">
                        <?php esc_html_e('Test Connection', 'spiralengine'); ?>
                    </button>
                    <p class="description">
                        <?php 
                        printf(
                            esc_html__('Get your API key from %s', 'spiralengine'),
                            '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>'
                        ); 
                        ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_openai_model"><?php esc_html_e('Default Model', 'spiralengine'); ?></label>
                </th>
                <td>
                    <select id="spiralengine_openai_model" name="spiralengine_api_settings[openai_model]">
                        <?php
                        $models = array(
                            'gpt-4' => 'GPT-4 (Most capable)',
                            'gpt-4-turbo-preview' => 'GPT-4 Turbo (Faster)',
                            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Cost effective)',
                            'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K (Extended context)'
                        );
                        
                        $current_model = $settings['openai_model'] ?? 'gpt-3.5-turbo';
                        
                        foreach ($models as $model => $name) {
                            echo '<option value="' . esc_attr($model) . '"' . selected($current_model, $model, false) . '>';
                            echo esc_html($name);
                            echo '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_openai_temperature"><?php esc_html_e('Temperature', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="range" 
                           id="spiralengine_openai_temperature" 
                           name="spiralengine_api_settings[openai_temperature]" 
                           value="<?php echo esc_attr($settings['openai_temperature'] ?? '0.7'); ?>" 
                           min="0" 
                           max="2" 
                           step="0.1" 
                           class="spiralengine-range-slider" />
                    <span class="spiralengine-range-value"><?php echo esc_html($settings['openai_temperature'] ?? '0.7'); ?></span>
                    <p class="description"><?php esc_html_e('Controls randomness: 0 is focused, 2 is very random', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_openai_max_tokens"><?php esc_html_e('Max Tokens', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="spiralengine_openai_max_tokens" 
                           name="spiralengine_api_settings[openai_max_tokens]" 
                           value="<?php echo esc_attr($settings['openai_max_tokens'] ?? '2000'); ?>" 
                           min="100" 
                           max="4000" 
                           class="small-text" />
                    <p class="description"><?php esc_html_e('Maximum length of generated responses', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Rate Limiting', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_api_settings[enable_rate_limiting]" 
                                   value="1" 
                                   <?php checked($settings['enable_rate_limiting'] ?? true); ?> />
                            <?php esc_html_e('Enable rate limiting', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <?php esc_html_e('Requests per minute:', 'spiralengine'); ?>
                            <input type="number" 
                                   name="spiralengine_api_settings[requests_per_minute]" 
                                   value="<?php echo esc_attr($settings['requests_per_minute'] ?? '60'); ?>" 
                                   min="1" 
                                   max="500" 
                                   class="small-text" />
                        </label><br />
                        
                        <label>
                            <?php esc_html_e('Tokens per minute:', 'spiralengine'); ?>
                            <input type="number" 
                                   name="spiralengine_api_settings[tokens_per_minute]" 
                                   value="<?php echo esc_attr($settings['tokens_per_minute'] ?? '60000'); ?>" 
                                   min="1000" 
                                   max="150000" 
                                   class="regular-text" />
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Webhook Configuration', 'spiralengine'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Webhook URL', 'spiralengine'); ?></label>
                </th>
                <td>
                    <code><?php echo esc_url(get_rest_url(null, 'spiralengine/v1/webhook')); ?></code>
                    <button type="button" class="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent)">
                        <?php esc_html_e('Copy', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_webhook_secret"><?php esc_html_e('Webhook Secret', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="spiralengine_webhook_secret" 
                           name="spiralengine_api_settings[webhook_secret]" 
                           value="<?php echo esc_attr($settings['webhook_secret'] ?? wp_generate_password(32, false)); ?>" 
                           class="regular-text" 
                           readonly />
                    <button type="button" class="button" id="spiralengine-regenerate-webhook-secret">
                        <?php esc_html_e('Regenerate', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Third-party Integrations', 'spiralengine'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Google Calendar', 'spiralengine'); ?></label>
                </th>
                <td>
                    <?php if (empty($settings['google_calendar_connected'])) : ?>
                        <button type="button" class="button" id="spiralengine-connect-google-calendar">
                            <?php esc_html_e('Connect Google Calendar', 'spiralengine'); ?>
                        </button>
                    <?php else : ?>
                        <span class="spiralengine-connected">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Connected', 'spiralengine'); ?>
                        </span>
                        <button type="button" class="button" id="spiralengine-disconnect-google-calendar">
                            <?php esc_html_e('Disconnect', 'spiralengine'); ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Zapier', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           name="spiralengine_api_settings[zapier_webhook]" 
                           value="<?php echo esc_attr($settings['zapier_webhook'] ?? ''); ?>" 
                           placeholder="https://hooks.zapier.com/..." 
                           class="regular-text" />
                    <p class="description"><?php esc_html_e('Enter your Zapier webhook URL to send events', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Performance tab
     */
    public function render_performance_tab() {
        settings_fields('spiralengine_performance');
        $settings = get_option('spiralengine_performance_settings', array());
        ?>
        <h2><?php esc_html_e('Cache Settings', 'spiralengine'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Enable Caching', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_performance_settings[enable_object_cache]" 
                                   value="1" 
                                   <?php checked($settings['enable_object_cache'] ?? true); ?> />
                            <?php esc_html_e('Object Cache', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_performance_settings[enable_page_cache]" 
                                   value="1" 
                                   <?php checked($settings['enable_page_cache'] ?? false); ?> />
                            <?php esc_html_e('Page Cache', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_performance_settings[enable_query_cache]" 
                                   value="1" 
                                   <?php checked($settings['enable_query_cache'] ?? true); ?> />
                            <?php esc_html_e('Query Cache', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_cache_ttl"><?php esc_html_e('Cache Lifetime', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="spiralengine_cache_ttl" 
                           name="spiralengine_performance_settings[cache_ttl]" 
                           value="<?php echo esc_attr($settings['cache_ttl'] ?? '3600'); ?>" 
                           min="60" 
                           max="86400" 
                           class="small-text" />
                    <?php esc_html_e('seconds', 'spiralengine'); ?>
                    <p class="description"><?php esc_html_e('How long to keep cached data', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Asset Optimization', 'spiralengine'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Optimize Assets', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_performance_settings[minify_css]" 
                                   value="1" 
                                   <?php checked($settings['minify_css'] ?? true); ?> />
                            <?php esc_html_e('Minify CSS', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_performance_settings[minify_js]" 
                                   value="1" 
                                   <?php checked($settings['minify_js'] ?? true); ?> />
                            <?php esc_html_e('Minify JavaScript', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_performance_settings[combine_assets]" 
                                   value="1" 
                                   <?php checked($settings['combine_assets'] ?? false); ?> />
                            <?php esc_html_e('Combine files', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_performance_settings[lazy_load_images]" 
                                   value="1" 
                                   <?php checked($settings['lazy_load_images'] ?? true); ?> />
                            <?php esc_html_e('Lazy load images', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Database Optimization', 'spiralengine'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Automatic Optimization', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="spiralengine_performance_settings[auto_optimize_db]" 
                               value="1" 
                               <?php checked($settings['auto_optimize_db'] ?? true); ?> />
                        <?php esc_html_e('Automatically optimize database tables', 'spiralengine'); ?>
                    </label>
                    
                    <p>
                        <label>
                            <?php esc_html_e('Frequency:', 'spiralengine'); ?>
                            <select name="spiralengine_performance_settings[optimize_frequency]">
                                <option value="daily" <?php selected($settings['optimize_frequency'] ?? 'weekly', 'daily'); ?>>
                                    <?php esc_html_e('Daily', 'spiralengine'); ?>
                                </option>
                                <option value="weekly" <?php selected($settings['optimize_frequency'] ?? 'weekly', 'weekly'); ?>>
                                    <?php esc_html_e('Weekly', 'spiralengine'); ?>
                                </option>
                                <option value="monthly" <?php selected($settings['optimize_frequency'] ?? 'weekly', 'monthly'); ?>>
                                    <?php esc_html_e('Monthly', 'spiralengine'); ?>
                                </option>
                            </select>
                        </label>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_slow_query_threshold"><?php esc_html_e('Slow Query Threshold', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="spiralengine_slow_query_threshold" 
                           name="spiralengine_performance_settings[slow_query_threshold]" 
                           value="<?php echo esc_attr($settings['slow_query_threshold'] ?? '1.0'); ?>" 
                           min="0.1" 
                           max="10" 
                           step="0.1" 
                           class="small-text" />
                    <?php esc_html_e('seconds', 'spiralengine'); ?>
                    <p class="description"><?php esc_html_e('Log queries that take longer than this', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        
        <div class="spiralengine-performance-stats">
            <h2><?php esc_html_e('Current Performance', 'spiralengine'); ?></h2>
            <div id="spiralengine-performance-metrics">
                <div class="spiralengine-loading">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading performance metrics...', 'spiralengine'); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Security tab
     */
    public function render_security_tab() {
        settings_fields('spiralengine_security');
        $settings = get_option('spiralengine_security_settings', array());
        ?>
        <h2><?php esc_html_e('Security Settings', 'spiralengine'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Login Security', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[enable_2fa]" 
                                   value="1" 
                                   <?php checked($settings['enable_2fa'] ?? false); ?> />
                            <?php esc_html_e('Enable two-factor authentication', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[limit_login_attempts]" 
                                   value="1" 
                                   <?php checked($settings['limit_login_attempts'] ?? true); ?> />
                            <?php esc_html_e('Limit login attempts', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <?php esc_html_e('Max attempts:', 'spiralengine'); ?>
                            <input type="number" 
                                   name="spiralengine_security_settings[max_login_attempts]" 
                                   value="<?php echo esc_attr($settings['max_login_attempts'] ?? '5'); ?>" 
                                   min="3" 
                                   max="10" 
                                   class="small-text" />
                        </label><br />
                        
                        <label>
                            <?php esc_html_e('Lockout duration:', 'spiralengine'); ?>
                            <input type="number" 
                                   name="spiralengine_security_settings[lockout_duration]" 
                                   value="<?php echo esc_attr($settings['lockout_duration'] ?? '30'); ?>" 
                                   min="5" 
                                   max="1440" 
                                   class="small-text" />
                            <?php esc_html_e('minutes', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Data Encryption', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[encrypt_sensitive_data]" 
                                   value="1" 
                                   <?php checked($settings['encrypt_sensitive_data'] ?? true); ?> />
                            <?php esc_html_e('Encrypt sensitive user data', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[encrypt_api_keys]" 
                                   value="1" 
                                   <?php checked($settings['encrypt_api_keys'] ?? true); ?> />
                            <?php esc_html_e('Encrypt API keys', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[encrypt_exports]" 
                                   value="1" 
                                   <?php checked($settings['encrypt_exports'] ?? false); ?> />
                            <?php esc_html_e('Encrypt data exports', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_encryption_key"><?php esc_html_e('Encryption Key', 'spiralengine'); ?></label>
                </th>
                <td>
                    <?php
                    $encryption_key = $settings['encryption_key'] ?? '';
                    if (empty($encryption_key)) {
                        $encryption_key = base64_encode(wp_generate_password(32, true, true));
                    }
                    ?>
                    <input type="text" 
                           id="spiralengine_encryption_key" 
                           name="spiralengine_security_settings[encryption_key]" 
                           value="<?php echo esc_attr($encryption_key); ?>" 
                           class="regular-text" 
                           readonly />
                    <button type="button" class="button" id="spiralengine-regenerate-encryption-key">
                        <?php esc_html_e('Regenerate', 'spiralengine'); ?>
                    </button>
                    <p class="description">
                        <strong><?php esc_html_e('Warning:', 'spiralengine'); ?></strong>
                        <?php esc_html_e('Changing this key will make existing encrypted data unreadable.', 'spiralengine'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Session Security', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[secure_sessions]" 
                                   value="1" 
                                   <?php checked($settings['secure_sessions'] ?? true); ?> />
                            <?php esc_html_e('Use secure session cookies', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[regenerate_session]" 
                                   value="1" 
                                   <?php checked($settings['regenerate_session'] ?? true); ?> />
                            <?php esc_html_e('Regenerate session ID on login', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[single_session]" 
                                   value="1" 
                                   <?php checked($settings['single_session'] ?? false); ?> />
                            <?php esc_html_e('Allow only one active session per user', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Content Security', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[enable_csp]" 
                                   value="1" 
                                   <?php checked($settings['enable_csp'] ?? false); ?> />
                            <?php esc_html_e('Enable Content Security Policy', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[enable_xss_protection]" 
                                   value="1" 
                                   <?php checked($settings['enable_xss_protection'] ?? true); ?> />
                            <?php esc_html_e('Enable XSS protection', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[sanitize_uploads]" 
                                   value="1" 
                                   <?php checked($settings['sanitize_uploads'] ?? true); ?> />
                            <?php esc_html_e('Sanitize file uploads', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Audit Logging', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_security_settings[enable_audit_log]" 
                                   value="1" 
                                   <?php checked($settings['enable_audit_log'] ?? true); ?> />
                            <?php esc_html_e('Enable audit logging', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <?php esc_html_e('Log retention:', 'spiralengine'); ?>
                            <input type="number" 
                                   name="spiralengine_security_settings[audit_log_retention]" 
                                   value="<?php echo esc_attr($settings['audit_log_retention'] ?? '90'); ?>" 
                                   min="30" 
                                   max="365" 
                                   class="small-text" />
                            <?php esc_html_e('days', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Backup tab
     */
    public function render_backup_tab() {
        settings_fields('spiralengine_backup');
        $settings = get_option('spiralengine_backup_settings', array());
        ?>
        <h2><?php esc_html_e('Backup Settings', 'spiralengine'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Automatic Backups', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="spiralengine_backup_settings[enable_auto_backup]" 
                               value="1" 
                               <?php checked($settings['enable_auto_backup'] ?? true); ?> />
                        <?php esc_html_e('Enable automatic backups', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Backup Schedule', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <?php esc_html_e('Full backup:', 'spiralengine'); ?>
                            <select name="spiralengine_backup_settings[full_backup_schedule]">
                                <option value="disabled" <?php selected($settings['full_backup_schedule'] ?? 'weekly', 'disabled'); ?>>
                                    <?php esc_html_e('Disabled', 'spiralengine'); ?>
                                </option>
                                <option value="daily" <?php selected($settings['full_backup_schedule'] ?? 'weekly', 'daily'); ?>>
                                    <?php esc_html_e('Daily', 'spiralengine'); ?>
                                </option>
                                <option value="weekly" <?php selected($settings['full_backup_schedule'] ?? 'weekly', 'weekly'); ?>>
                                    <?php esc_html_e('Weekly', 'spiralengine'); ?>
                                </option>
                                <option value="monthly" <?php selected($settings['full_backup_schedule'] ?? 'weekly', 'monthly'); ?>>
                                    <?php esc_html_e('Monthly', 'spiralengine'); ?>
                                </option>
                            </select>
                        </label><br />
                        
                        <label>
                            <?php esc_html_e('Database backup:', 'spiralengine'); ?>
                            <select name="spiralengine_backup_settings[db_backup_schedule]">
                                <option value="disabled" <?php selected($settings['db_backup_schedule'] ?? 'daily', 'disabled'); ?>>
                                    <?php esc_html_e('Disabled', 'spiralengine'); ?>
                                </option>
                                <option value="daily" <?php selected($settings['db_backup_schedule'] ?? 'daily', 'daily'); ?>>
                                    <?php esc_html_e('Daily', 'spiralengine'); ?>
                                </option>
                                <option value="twice_daily" <?php selected($settings['db_backup_schedule'] ?? 'daily', 'twice_daily'); ?>>
                                    <?php esc_html_e('Twice Daily', 'spiralengine'); ?>
                                </option>
                                <option value="hourly" <?php selected($settings['db_backup_schedule'] ?? 'daily', 'hourly'); ?>>
                                    <?php esc_html_e('Hourly', 'spiralengine'); ?>
                                </option>
                            </select>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_backup_time"><?php esc_html_e('Backup Time', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="time" 
                           id="spiralengine_backup_time" 
                           name="spiralengine_backup_settings[backup_time]" 
                           value="<?php echo esc_attr($settings['backup_time'] ?? '02:00'); ?>" />
                    <p class="description"><?php esc_html_e('Time when scheduled backups should run (in your timezone)', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Backup Storage', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_backup_settings[store_locally]" 
                                   value="1" 
                                   <?php checked($settings['store_locally'] ?? true); ?> />
                            <?php esc_html_e('Store locally', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_backup_settings[store_cloud]" 
                                   value="1" 
                                   <?php checked($settings['store_cloud'] ?? false); ?> />
                            <?php esc_html_e('Store in cloud', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_backup_retention"><?php esc_html_e('Backup Retention', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="spiralengine_backup_retention" 
                           name="spiralengine_backup_settings[retention_days]" 
                           value="<?php echo esc_attr($settings['retention_days'] ?? '30'); ?>" 
                           min="7" 
                           max="365" 
                           class="small-text" />
                    <?php esc_html_e('days', 'spiralengine'); ?>
                    <p class="description"><?php esc_html_e('Automatically delete backups older than this', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Backup Options', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_backup_settings[compress_backups]" 
                                   value="1" 
                                   <?php checked($settings['compress_backups'] ?? true); ?> />
                            <?php esc_html_e('Compress backups', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_backup_settings[encrypt_backups]" 
                                   value="1" 
                                   <?php checked($settings['encrypt_backups'] ?? true); ?> />
                            <?php esc_html_e('Encrypt backups', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_backup_settings[email_notifications]" 
                                   value="1" 
                                   <?php checked($settings['email_notifications'] ?? true); ?> />
                            <?php esc_html_e('Email backup notifications', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Manual Backup & Restore', 'spiralengine'); ?></h2>
        <div class="spiralengine-backup-actions">
            <p>
                <button type="button" class="button button-primary" id="spiralengine-create-backup">
                    <?php esc_html_e('Create Backup Now', 'spiralengine'); ?>
                </button>
                <button type="button" class="button" id="spiralengine-restore-backup">
                    <?php esc_html_e('Restore from Backup', 'spiralengine'); ?>
                </button>
            </p>
        </div>
        
        <div class="spiralengine-backup-list">
            <h3><?php esc_html_e('Available Backups', 'spiralengine'); ?></h3>
            <div id="spiralengine-backups-table">
                <div class="spiralengine-loading">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading backups...', 'spiralengine'); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Maintenance tab
     */
    public function render_maintenance_tab() {
        settings_fields('spiralengine_maintenance');
        $settings = get_option('spiralengine_maintenance_settings', array());
        ?>
        <h2><?php esc_html_e('Maintenance Mode', 'spiralengine'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="spiralengine_maintenance_mode"><?php esc_html_e('Maintenance Mode', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="spiralengine_maintenance_mode"
                               name="spiralengine_maintenance_settings[enabled]" 
                               value="1" 
                               <?php checked($settings['enabled'] ?? false); ?> />
                        <?php esc_html_e('Enable maintenance mode', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Show maintenance page to visitors while you perform updates', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_maintenance_message"><?php esc_html_e('Maintenance Message', 'spiralengine'); ?></label>
                </th>
                <td>
                    <textarea id="spiralengine_maintenance_message" 
                              name="spiralengine_maintenance_settings[message]" 
                              rows="4" 
                              class="large-text"><?php echo esc_textarea($settings['message'] ?? __('We are currently performing maintenance. We\'ll be back online shortly!', 'spiralengine')); ?></textarea>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_maintenance_return"><?php esc_html_e('Expected Return', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="datetime-local" 
                           id="spiralengine_maintenance_return" 
                           name="spiralengine_maintenance_settings[return_time]" 
                           value="<?php echo esc_attr($settings['return_time'] ?? ''); ?>" />
                    <p class="description"><?php esc_html_e('When you expect the site to be back online', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Maintenance Tools', 'spiralengine'); ?></h2>
        <div class="spiralengine-maintenance-tools">
            <div class="tool-section">
                <h3><?php esc_html_e('Database Maintenance', 'spiralengine'); ?></h3>
                <p>
                    <button type="button" class="button" id="spiralengine-optimize-db">
                        <?php esc_html_e('Optimize Database', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button" id="spiralengine-repair-tables">
                        <?php esc_html_e('Repair Tables', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button" id="spiralengine-clean-data">
                        <?php esc_html_e('Clean Old Data', 'spiralengine'); ?>
                    </button>
                </p>
            </div>
            
            <div class="tool-section">
                <h3><?php esc_html_e('Cache Management', 'spiralengine'); ?></h3>
                <p>
                    <button type="button" class="button" id="spiralengine-clear-all-cache">
                        <?php esc_html_e('Clear All Cache', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button" id="spiralengine-clear-object-cache">
                        <?php esc_html_e('Clear Object Cache', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button" id="spiralengine-clear-transients">
                        <?php esc_html_e('Clear Transients', 'spiralengine'); ?>
                    </button>
                </p>
            </div>
            
            <div class="tool-section">
                <h3><?php esc_html_e('System Diagnostics', 'spiralengine'); ?></h3>
                <p>
                    <button type="button" class="button" id="spiralengine-run-diagnostics">
                        <?php esc_html_e('Run Diagnostics', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button" id="spiralengine-check-conflicts">
                        <?php esc_html_e('Check Plugin Conflicts', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button" id="spiralengine-fix-permissions">
                        <?php esc_html_e('Fix File Permissions', 'spiralengine'); ?>
                    </button>
                </p>
            </div>
        </div>
        
        <div id="spiralengine-maintenance-results" class="spiralengine-results-panel" style="display: none;">
            <h3><?php esc_html_e('Results', 'spiralengine'); ?></h3>
            <div class="results-content"></div>
        </div>
        <?php
    }
    
    /**
     * Render Advanced tab
     */
    public function render_advanced_tab() {
        settings_fields('spiralengine_advanced');
        $settings = get_option('spiralengine_advanced_settings', array());
        ?>
        <h2><?php esc_html_e('Advanced Settings', 'spiralengine'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Debug Mode', 'spiralengine'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_advanced_settings[debug_mode]" 
                                   value="1" 
                                   <?php checked($settings['debug_mode'] ?? false); ?> />
                            <?php esc_html_e('Enable debug mode', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_advanced_settings[log_errors]" 
                                   value="1" 
                                   <?php checked($settings['log_errors'] ?? true); ?> />
                            <?php esc_html_e('Log errors to file', 'spiralengine'); ?>
                        </label><br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_advanced_settings[display_errors]" 
                                   value="1" 
                                   <?php checked($settings['display_errors'] ?? false); ?> />
                            <?php esc_html_e('Display errors (development only)', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_uninstall_data"><?php esc_html_e('Uninstall Options', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="spiralengine_uninstall_data"
                               name="spiralengine_advanced_settings[delete_data_on_uninstall]" 
                               value="1" 
                               <?php checked($settings['delete_data_on_uninstall'] ?? false); ?> />
                        <?php esc_html_e('Delete all data when plugin is uninstalled', 'spiralengine'); ?>
                    </label>
                    <p class="description">
                        <strong><?php esc_html_e('Warning:', 'spiralengine'); ?></strong>
                        <?php esc_html_e('This will permanently delete all SPIRAL Engine data including user data, assessments, and settings.', 'spiralengine'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Beta Features', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="spiralengine_advanced_settings[enable_beta_features]" 
                               value="1" 
                               <?php checked($settings['enable_beta_features'] ?? false); ?> />
                        <?php esc_html_e('Enable beta features', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Access new features that are still in development', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_custom_css"><?php esc_html_e('Custom CSS', 'spiralengine'); ?></label>
                </th>
                <td>
                    <textarea id="spiralengine_custom_css" 
                              name="spiralengine_advanced_settings[custom_css]" 
                              rows="10" 
                              class="large-text code"><?php echo esc_textarea($settings['custom_css'] ?? ''); ?></textarea>
                    <p class="description"><?php esc_html_e('Add custom CSS to customize the appearance of SPIRAL Engine', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="spiralengine_custom_js"><?php esc_html_e('Custom JavaScript', 'spiralengine'); ?></label>
                </th>
                <td>
                    <textarea id="spiralengine_custom_js" 
                              name="spiralengine_advanced_settings[custom_js]" 
                              rows="10" 
                              class="large-text code"><?php echo esc_textarea($settings['custom_js'] ?? ''); ?></textarea>
                    <p class="description"><?php esc_html_e('Add custom JavaScript for advanced functionality', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('System Information', 'spiralengine'); ?></h2>
        <div class="spiralengine-system-info">
            <textarea readonly class="large-text" rows="20"><?php echo esc_textarea($this->get_system_info()); ?></textarea>
            <p>
                <button type="button" class="button" id="spiralengine-copy-system-info">
                    <?php esc_html_e('Copy System Info', 'spiralengine'); ?>
                </button>
                <button type="button" class="button" id="spiralengine-download-system-info">
                    <?php esc_html_e('Download System Info', 'spiralengine'); ?>
                </button>
            </p>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'spiralengine-settings') === false) {
            return;
        }
        
        wp_enqueue_style(
            'spiralengine-settings',
            SPIRALENGINE_PLUGIN_URL . 'assets/css/admin/settings.css',
            array(),
            SPIRALENGINE_VERSION
        );
        
        wp_enqueue_script(
            'spiralengine-settings',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/admin/settings.js',
            array('jquery', 'wp-util'),
            SPIRALENGINE_VERSION,
            true
        );
        
        wp_localize_script('spiralengine-settings', 'spiralengineSettings', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_settings'),
            'strings' => array(
                'confirmReset' => __('Are you sure you want to reset all settings to defaults?', 'spiralengine'),
                'confirmImport' => __('This will overwrite your current settings. Continue?', 'spiralengine'),
                'saveSuccess' => __('Settings saved successfully', 'spiralengine'),
                'saveError' => __('Error saving settings', 'spiralengine'),
                'testingConnection' => __('Testing connection...', 'spiralengine'),
                'connectionSuccess' => __('Connection successful!', 'spiralengine'),
                'connectionError' => __('Connection failed', 'spiralengine')
            )
        ));
    }
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=spiralengine-settings') . '">' . __('Settings', 'spiralengine') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Sanitization callbacks
     */
    public function sanitize_general_settings($input) {
        $sanitized = array();
        
        $sanitized['site_name'] = sanitize_text_field($input['site_name'] ?? '');
        $sanitized['timezone'] = sanitize_text_field($input['timezone'] ?? '');
        
        // Handle date format
        if (isset($input['date_format']) && $input['date_format'] === 'custom') {
            $sanitized['date_format'] = sanitize_text_field($input['date_format_custom'] ?? '');
        } else {
            $sanitized['date_format'] = sanitize_text_field($input['date_format'] ?? '');
        }
        
        // Handle time format
        if (isset($input['time_format']) && $input['time_format'] === 'custom') {
            $sanitized['time_format'] = sanitize_text_field($input['time_format_custom'] ?? '');
        } else {
            $sanitized['time_format'] = sanitize_text_field($input['time_format'] ?? '');
        }
        
        // Checkboxes
        $checkboxes = array(
            'enable_assessments',
            'enable_episodes',
            'enable_ai',
            'enable_analytics',
            'enable_notifications'
        );
        
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = !empty($input[$checkbox]);
        }
        
        $sanitized['default_language'] = sanitize_text_field($input['default_language'] ?? 'en_US');
        
        return $sanitized;
    }
    
    public function sanitize_user_settings($input) {
        $sanitized = array();
        
        // Checkboxes
        $checkboxes = array(
            'allow_registration',
            'require_phone',
            'require_birthdate',
            'require_timezone',
            'enable_avatar',
            'anonymous_mode',
            'data_export',
            'data_deletion'
        );
        
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = !empty($input[$checkbox]);
        }
        
        $sanitized['default_role'] = sanitize_text_field($input['default_role'] ?? 'subscriber');
        $sanitized['session_timeout'] = absint($input['session_timeout'] ?? 30);
        $sanitized['data_retention_days'] = absint($input['data_retention_days'] ?? 365);
        
        return $sanitized;
    }
    
    public function sanitize_permission_settings($input) {
        $sanitized = array();
        
        // Process role permissions
        if (isset($input) && is_array($input)) {
            foreach ($input as $role => $capabilities) {
                if ($role === 'custom_capabilities') {
                    continue;
                }
                
                $sanitized[$role] = array();
                
                if (is_array($capabilities)) {
                    foreach ($capabilities as $cap => $value) {
                        $sanitized[$role][$cap] = !empty($value);
                    }
                }
            }
        }
        
        // Process custom capabilities
        if (isset($input['custom_capabilities']) && is_array($input['custom_capabilities'])) {
            $sanitized['custom_capabilities'] = array_filter(array_map('sanitize_key', $input['custom_capabilities']));
        }
        
        // Update roles with capabilities
        $this->update_role_capabilities($sanitized);
        
        return $sanitized;
    }
    
    public function sanitize_api_settings($input) {
        $sanitized = array();
        
        // OpenAI settings
        $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key'] ?? '');
        
        // Encrypt API key if not already encrypted
        if (!empty($sanitized['openai_api_key']) && strpos($sanitized['openai_api_key'], 'enc:') !== 0) {
            $encryption = new SPIRALENGINE_Flexible_Encryption();
            $sanitized['openai_api_key'] = 'enc:' . $encryption->encrypt($sanitized['openai_api_key']);
        }
        
        $sanitized['openai_model'] = sanitize_text_field($input['openai_model'] ?? 'gpt-3.5-turbo');
        $sanitized['openai_temperature'] = floatval($input['openai_temperature'] ?? 0.7);
        $sanitized['openai_max_tokens'] = absint($input['openai_max_tokens'] ?? 2000);
        
        // Rate limiting
        $sanitized['enable_rate_limiting'] = !empty($input['enable_rate_limiting']);
        $sanitized['requests_per_minute'] = absint($input['requests_per_minute'] ?? 60);
        $sanitized['tokens_per_minute'] = absint($input['tokens_per_minute'] ?? 60000);
        
        // Webhook
        $sanitized['webhook_secret'] = sanitize_text_field($input['webhook_secret'] ?? '');
        
        // Third-party integrations
        $sanitized['zapier_webhook'] = esc_url_raw($input['zapier_webhook'] ?? '');
        
        return $sanitized;
    }
    
    public function sanitize_performance_settings($input) {
        $sanitized = array();
        
        // Cache settings
        $checkboxes = array(
            'enable_object_cache',
            'enable_page_cache',
            'enable_query_cache',
            'minify_css',
            'minify_js',
            'combine_assets',
            'lazy_load_images',
            'auto_optimize_db'
        );
        
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = !empty($input[$checkbox]);
        }
        
        $sanitized['cache_ttl'] = absint($input['cache_ttl'] ?? 3600);
        $sanitized['optimize_frequency'] = sanitize_text_field($input['optimize_frequency'] ?? 'weekly');
        $sanitized['slow_query_threshold'] = floatval($input['slow_query_threshold'] ?? 1.0);
        
        return $sanitized;
    }
    
    public function sanitize_security_settings($input) {
        $sanitized = array();
        
        // Security checkboxes
        $checkboxes = array(
            'enable_2fa',
            'limit_login_attempts',
            'encrypt_sensitive_data',
            'encrypt_api_keys',
            'encrypt_exports',
            'secure_sessions',
            'regenerate_session',
            'single_session',
            'enable_csp',
            'enable_xss_protection',
            'sanitize_uploads',
            'enable_audit_log'
        );
        
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = !empty($input[$checkbox]);
        }
        
        $sanitized['max_login_attempts'] = absint($input['max_login_attempts'] ?? 5);
        $sanitized['lockout_duration'] = absint($input['lockout_duration'] ?? 30);
        $sanitized['encryption_key'] = sanitize_text_field($input['encryption_key'] ?? '');
        $sanitized['audit_log_retention'] = absint($input['audit_log_retention'] ?? 90);
        
        return $sanitized;
    }
    
    public function sanitize_backup_settings($input) {
        $sanitized = array();
        
        // Backup checkboxes
        $checkboxes = array(
            'enable_auto_backup',
            'store_locally',
            'store_cloud',
            'compress_backups',
            'encrypt_backups',
            'email_notifications'
        );
        
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = !empty($input[$checkbox]);
        }
        
        $sanitized['full_backup_schedule'] = sanitize_text_field($input['full_backup_schedule'] ?? 'weekly');
        $sanitized['db_backup_schedule'] = sanitize_text_field($input['db_backup_schedule'] ?? 'daily');
        $sanitized['backup_time'] = sanitize_text_field($input['backup_time'] ?? '02:00');
        $sanitized['retention_days'] = absint($input['retention_days'] ?? 30);
        
        return $sanitized;
    }
    
    public function sanitize_maintenance_settings($input) {
        $sanitized = array();
        
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['message'] = wp_kses_post($input['message'] ?? '');
        $sanitized['return_time'] = sanitize_text_field($input['return_time'] ?? '');
        
        return $sanitized;
    }
    
    public function sanitize_advanced_settings($input) {
        $sanitized = array();
        
        // Advanced checkboxes
        $checkboxes = array(
            'debug_mode',
            'log_errors',
            'display_errors',
            'delete_data_on_uninstall',
            'enable_beta_features'
        );
        
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = !empty($input[$checkbox]);
        }
        
        $sanitized['custom_css'] = wp_strip_all_tags($input['custom_css'] ?? '');
        $sanitized['custom_js'] = wp_strip_all_tags($input['custom_js'] ?? '');
        
        return $sanitized;
    }
    
    /**
     * Update role capabilities
     */
    private function update_role_capabilities($permissions) {
        foreach ($permissions as $role_name => $capabilities) {
            if ($role_name === 'custom_capabilities') {
                continue;
            }
            
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }
            
            // Remove all SPIRAL Engine capabilities first
            $all_caps = array(
                'spiralengine_view_dashboard',
                'spiralengine_manage_assessments',
                'spiralengine_view_episodes',
                'spiralengine_manage_episodes',
                'spiralengine_use_ai',
                'spiralengine_view_analytics',
                'spiralengine_export_data',
                'spiralengine_manage_users',
                'spiralengine_manage_settings'
            );
            
            // Add custom capabilities
            if (isset($permissions['custom_capabilities'])) {
                $all_caps = array_merge($all_caps, $permissions['custom_capabilities']);
            }
            
            // Remove all capabilities
            foreach ($all_caps as $cap) {
                $role->remove_cap($cap);
            }
            
            // Add enabled capabilities
            foreach ($capabilities as $cap => $enabled) {
                if ($enabled) {
                    $role->add_cap($cap);
                }
            }
        }
    }
    
    /**
     * Get system info
     */
    private function get_system_info() {
        global $wpdb;
        
        $info = "### SPIRAL Engine System Information ###\n\n";
        
        // Plugin info
        $info .= "Plugin Version: " . SPIRALENGINE_VERSION . "\n";
        $info .= "Plugin Directory: " . SPIRALENGINE_PLUGIN_DIR . "\n\n";
        
        // WordPress info
        $info .= "WordPress Version: " . get_bloginfo('version') . "\n";
        $info .= "Site URL: " . get_site_url() . "\n";
        $info .= "Home URL: " . get_home_url() . "\n";
        $info .= "Multisite: " . (is_multisite() ? 'Yes' : 'No') . "\n";
        $info .= "Debug Mode: " . (WP_DEBUG ? 'Enabled' : 'Disabled') . "\n\n";
        
        // Server info
        $info .= "PHP Version: " . PHP_VERSION . "\n";
        $info .= "MySQL Version: " . $wpdb->db_version() . "\n";
        $info .= "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
        $info .= "Memory Limit: " . ini_get('memory_limit') . "\n";
        $info .= "Max Execution Time: " . ini_get('max_execution_time') . "\n";
        $info .= "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n\n";
        
        // Active theme
        $theme = wp_get_theme();
        $info .= "Active Theme: " . $theme->get('Name') . " " . $theme->get('Version') . "\n\n";
        
        // Active plugins
        $plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        
        $info .= "Active Plugins:\n";
        foreach ($plugins as $plugin_path => $plugin_data) {
            if (in_array($plugin_path, $active_plugins)) {
                $info .= "- " . $plugin_data['Name'] . " " . $plugin_data['Version'] . "\n";
            }
        }
        
        return $info;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_save_settings() {
        check_ajax_referer('spiralengine_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $tab = sanitize_key($_POST['tab'] ?? 'general');
        $settings = $_POST['settings'] ?? array();
        
        // Save based on tab
        switch ($tab) {
            case 'general':
                update_option('spiralengine_general_settings', $this->sanitize_general_settings($settings));
                break;
                
            case 'user':
                update_option('spiralengine_user_settings', $this->sanitize_user_settings($settings));
                break;
                
            case 'permissions':
                update_option('spiralengine_permission_settings', $this->sanitize_permission_settings($settings));
                break;
                
            case 'api':
                update_option('spiralengine_api_settings', $this->sanitize_api_settings($settings));
                break;
                
            case 'performance':
                update_option('spiralengine_performance_settings', $this->sanitize_performance_settings($settings));
                break;
                
            case 'security':
                update_option('spiralengine_security_settings', $this->sanitize_security_settings($settings));
                break;
                
            case 'backup':
                update_option('spiralengine_backup_settings', $this->sanitize_backup_settings($settings));
                break;
                
            case 'maintenance':
                update_option('spiralengine_maintenance_settings', $this->sanitize_maintenance_settings($settings));
                break;
                
            case 'advanced':
                update_option('spiralengine_advanced_settings', $this->sanitize_advanced_settings($settings));
                break;
        }
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully', 'spiralengine')
        ));
    }
    
    public function ajax_reset_settings() {
        check_ajax_referer('spiralengine_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $tab = sanitize_key($_POST['tab'] ?? 'general');
        
        // Reset based on tab
        switch ($tab) {
            case 'general':
                delete_option('spiralengine_general_settings');
                break;
                
            case 'user':
                delete_option('spiralengine_user_settings');
                break;
                
            case 'permissions':
                delete_option('spiralengine_permission_settings');
                // Reset role capabilities to defaults
                $this->reset_role_capabilities();
                break;
                
            case 'api':
                delete_option('spiralengine_api_settings');
                break;
                
            case 'performance':
                delete_option('spiralengine_performance_settings');
                break;
                
            case 'security':
                delete_option('spiralengine_security_settings');
                break;
                
            case 'backup':
                delete_option('spiralengine_backup_settings');
                break;
                
            case 'maintenance':
                delete_option('spiralengine_maintenance_settings');
                break;
                
            case 'advanced':
                delete_option('spiralengine_advanced_settings');
                break;
        }
        
        wp_send_json_success(array(
            'message' => __('Settings reset to defaults', 'spiralengine')
        ));
    }
    
    public function ajax_export_settings() {
        check_ajax_referer('spiralengine_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        // Gather all settings
        $export_data = array(
            'version' => SPIRALENGINE_VERSION,
            'exported_at' => current_time('mysql'),
            'settings' => array(
                'general' => get_option('spiralengine_general_settings', array()),
                'user' => get_option('spiralengine_user_settings', array()),
                'permissions' => get_option('spiralengine_permission_settings', array()),
                'api' => get_option('spiralengine_api_settings', array()),
                'performance' => get_option('spiralengine_performance_settings', array()),
                'security' => get_option('spiralengine_security_settings', array()),
                'backup' => get_option('spiralengine_backup_settings', array()),
                'maintenance' => get_option('spiralengine_maintenance_settings', array()),
                'advanced' => get_option('spiralengine_advanced_settings', array())
            )
        );
        
        // Remove sensitive data
        if (isset($export_data['settings']['api']['openai_api_key'])) {
            $export_data['settings']['api']['openai_api_key'] = '[REDACTED]';
        }
        
        if (isset($export_data['settings']['security']['encryption_key'])) {
            $export_data['settings']['security']['encryption_key'] = '[REDACTED]';
        }
        
        // Generate filename
        $filename = 'spiralengine-settings-' . date('Y-m-d-His') . '.json';
        
        // Send headers
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen(json_encode($export_data)));
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    public function ajax_import_settings() {
        check_ajax_referer('spiralengine_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        if (empty($_FILES['import_file'])) {
            wp_send_json_error(__('No file uploaded', 'spiralengine'));
        }
        
        $file = $_FILES['import_file'];
        
        // Validate file type
        if ($file['type'] !== 'application/json') {
            wp_send_json_error(__('Invalid file type. Please upload a JSON file.', 'spiralengine'));
        }
        
        // Read file
        $content = file_get_contents($file['tmp_name']);
        $import_data = json_decode($content, true);
        
        if (!$import_data || !isset($import_data['settings'])) {
            wp_send_json_error(__('Invalid settings file', 'spiralengine'));
        }
        
        // Import settings
        foreach ($import_data['settings'] as $key => $settings) {
            // Skip if sensitive data was redacted
            if (isset($settings['openai_api_key']) && $settings['openai_api_key'] === '[REDACTED]') {
                unset($settings['openai_api_key']);
            }
            
            if (isset($settings['encryption_key']) && $settings['encryption_key'] === '[REDACTED]') {
                unset($settings['encryption_key']);
            }
            
            // Sanitize and save
            switch ($key) {
                case 'general':
                    update_option('spiralengine_general_settings', $this->sanitize_general_settings($settings));
                    break;
                    
                case 'user':
                    update_option('spiralengine_user_settings', $this->sanitize_user_settings($settings));
                    break;
                    
                case 'permissions':
                    update_option('spiralengine_permission_settings', $this->sanitize_permission_settings($settings));
                    break;
                    
                case 'api':
                    $current = get_option('spiralengine_api_settings', array());
                    // Preserve existing API keys if not in import
                    if (empty($settings['openai_api_key']) && !empty($current['openai_api_key'])) {
                        $settings['openai_api_key'] = $current['openai_api_key'];
                    }
                    update_option('spiralengine_api_settings', $this->sanitize_api_settings($settings));
                    break;
                    
                case 'performance':
                    update_option('spiralengine_performance_settings', $this->sanitize_performance_settings($settings));
                    break;
                    
                case 'security':
                    $current = get_option('spiralengine_security_settings', array());
                    // Preserve existing encryption key if not in import
                    if (empty($settings['encryption_key']) && !empty($current['encryption_key'])) {
                        $settings['encryption_key'] = $current['encryption_key'];
                    }
                    update_option('spiralengine_security_settings', $this->sanitize_security_settings($settings));
                    break;
                    
                case 'backup':
                    update_option('spiralengine_backup_settings', $this->sanitize_backup_settings($settings));
                    break;
                    
                case 'maintenance':
                    update_option('spiralengine_maintenance_settings', $this->sanitize_maintenance_settings($settings));
                    break;
                    
                case 'advanced':
                    update_option('spiralengine_advanced_settings', $this->sanitize_advanced_settings($settings));
                    break;
            }
        }
        
        wp_send_json_success(array(
            'message' => __('Settings imported successfully', 'spiralengine')
        ));
    }
    
    public function ajax_test_api_connection() {
        check_ajax_referer('spiralengine_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $service = sanitize_text_field($_POST['service'] ?? 'openai');
        
        switch ($service) {
            case 'openai':
                $result = $this->test_openai_connection();
                break;
                
            default:
                $result = array(
                    'success' => false,
                    'message' => __('Unknown service', 'spiralengine')
                );
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Test OpenAI connection
     */
    private function test_openai_connection() {
        $settings = get_option('spiralengine_api_settings', array());
        $api_key = $settings['openai_api_key'] ?? '';
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('API key not configured', 'spiralengine')
            );
        }
        
        // Decrypt API key if encrypted
        if (strpos($api_key, 'enc:') === 0) {
            $encryption = new SPIRALENGINE_Flexible_Encryption();
            $api_key = $encryption->decrypt(substr($api_key, 4));
        }
        
        // Test API
        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $models = array();
            
            if (isset($body['data'])) {
                foreach ($body['data'] as $model) {
                    if (strpos($model['id'], 'gpt') === 0) {
                        $models[] = $model['id'];
                    }
                }
            }
            
            return array(
                'success' => true,
                'message' => __('Connection successful', 'spiralengine'),
                'models' => $models
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('API returned status %d', 'spiralengine'), $status)
            );
        }
    }
    
    /**
     * Reset role capabilities to defaults
     */
    private function reset_role_capabilities() {
        // Default capabilities
        $defaults = array(
            'administrator' => array(
                'spiralengine_view_dashboard' => true,
                'spiralengine_manage_assessments' => true,
                'spiralengine_view_episodes' => true,
                'spiralengine_manage_episodes' => true,
                'spiralengine_use_ai' => true,
                'spiralengine_view_analytics' => true,
                'spiralengine_export_data' => true,
                'spiralengine_manage_users' => true,
                'spiralengine_manage_settings' => true
            ),
            'editor' => array(
                'spiralengine_view_dashboard' => true,
                'spiralengine_manage_assessments' => true,
                'spiralengine_view_episodes' => true,
                'spiralengine_manage_episodes' => true,
                'spiralengine_use_ai' => true,
                'spiralengine_view_analytics' => true,
                'spiralengine_export_data' => true
            ),
            'author' => array(
                'spiralengine_view_dashboard' => true,
                'spiralengine_view_episodes' => true,
                'spiralengine_use_ai' => true
            ),
            'contributor' => array(
                'spiralengine_view_dashboard' => true,
                'spiralengine_view_episodes' => true
            ),
            'subscriber' => array(
                'spiralengine_view_dashboard' => true
            )
        );
        
        // Apply defaults
        $this->update_role_capabilities($defaults);
    }
}

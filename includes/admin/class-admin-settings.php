<?php
/**
 * SpiralEngine Admin Settings Page
 *
 * @package    SpiralEngine
 * @subpackage Admin
 * @file       includes/admin/class-admin-settings.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SpiralEngine_Admin_Settings
 *
 * Manages all plugin settings and configuration
 */
class SpiralEngine_Admin_Settings {
    
    /**
     * Instance of this class
     *
     * @var SpiralEngine_Admin_Settings
     */
    private static $instance = null;
    
    /**
     * Settings tabs
     *
     * @var array
     */
    private $tabs = array();
    
    /**
     * Current active tab
     *
     * @var string
     */
    private $active_tab = '';
    
    /**
     * Settings option name
     *
     * @var string
     */
    const OPTION_NAME = 'spiralengine_settings';
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Get instance
     *
     * @return SpiralEngine_Admin_Settings
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize settings
     */
    public function init() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'process_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_spiralengine_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_spiralengine_test_connection', array($this, 'ajax_test_connection'));
        
        $this->setup_tabs();
    }
    
    /**
     * Setup settings tabs
     */
    private function setup_tabs() {
        $this->tabs = array(
            'general' => array(
                'title' => __('General', 'spiralengine'),
                'icon' => 'dashicons-admin-generic',
                'capability' => 'spiralengine_manage_settings'
            ),
            'membership' => array(
                'title' => __('Membership', 'spiralengine'),
                'icon' => 'dashicons-groups',
                'capability' => 'spiralengine_manage_settings'
            ),
            'ai' => array(
                'title' => __('AI Providers', 'spiralengine'),
                'icon' => 'dashicons-lightbulb',
                'capability' => 'spiralengine_manage_ai'
            ),
            'email' => array(
                'title' => __('Email', 'spiralengine'),
                'icon' => 'dashicons-email',
                'capability' => 'spiralengine_manage_settings'
            ),
            'notifications' => array(
                'title' => __('Notifications', 'spiralengine'),
                'icon' => 'dashicons-bell',
                'capability' => 'spiralengine_manage_settings'
            ),
            'api' => array(
                'title' => __('API', 'spiralengine'),
                'icon' => 'dashicons-admin-links',
                'capability' => 'spiralengine_manage_api'
            ),
            'advanced' => array(
                'title' => __('Advanced', 'spiralengine'),
                'icon' => 'dashicons-admin-tools',
                'capability' => 'spiralengine_manage_settings'
            ),
            'tools' => array(
                'title' => __('Tools', 'spiralengine'),
                'icon' => 'dashicons-hammer',
                'capability' => 'spiralengine_manage_settings'
            )
        );
        
        // Filter tabs for capability
        $this->tabs = array_filter($this->tabs, function($tab) {
            return current_user_can($tab['capability']);
        });
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'spiralengine_settings',
            self::OPTION_NAME,
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Enqueue scripts and styles
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'spiralengine-settings') === false) {
            return;
        }
        
        wp_enqueue_style('spiralengine-admin');
        wp_enqueue_script('spiralengine-admin');
        
        // Add settings-specific script
        wp_add_inline_script('spiralengine-admin', $this->get_inline_script());
    }
    
    /**
     * Get inline JavaScript
     *
     * @return string
     */
    private function get_inline_script() {
        return "
        jQuery(document).ready(function($) {
            // Tab switching
            $('.spiralengine-settings-tabs a').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                // Update URL
                var url = new URL(window.location);
                url.searchParams.set('tab', tab);
                window.history.pushState({}, '', url);
                
                // Switch active tab
                $('.spiralengine-settings-tabs li').removeClass('active');
                $(this).parent().addClass('active');
                
                // Show/hide content
                $('.spiralengine-settings-content').hide();
                $('#spiralengine-settings-' + tab).show();
            });
            
            // Settings form submission
            $('#spiralengine-settings-form').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var submitBtn = form.find('input[type=\"submit\"]');
                var originalText = submitBtn.val();
                
                submitBtn.val('" . __('Saving...', 'spiralengine') . "').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: form.serialize() + '&action=spiralengine_save_settings',
                    success: function(response) {
                        if (response.success) {
                            $('.spiralengine-notice').remove();
                            $('<div class=\"notice notice-success is-dismissible spiralengine-notice\"><p>' + response.data.message + '</p></div>')
                                .insertAfter('.wrap h1');
                        } else {
                            alert(response.data.message || '" . __('Error saving settings', 'spiralengine') . "');
                        }
                    },
                    complete: function() {
                        submitBtn.val(originalText).prop('disabled', false);
                    }
                });
            });
            
            // Test connection buttons
            $('.spiralengine-test-connection').on('click', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var provider = button.data('provider');
                var originalText = button.text();
                
                button.text('" . __('Testing...', 'spiralengine') . "').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_test_connection',
                        provider: provider,
                        nonce: '" . wp_create_nonce('spiralengine_test_connection') . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            button.addClass('button-primary').removeClass('button-secondary');
                            alert(response.data.message);
                        } else {
                            button.addClass('button-secondary').removeClass('button-primary');
                            alert(response.data.message || '" . __('Connection failed', 'spiralengine') . "');
                        }
                    },
                    complete: function() {
                        button.text(originalText).prop('disabled', false);
                    }
                });
            });
        });
        ";
    }
    
    /**
     * Render settings page
     */
    public function render_page() {
        if (!current_user_can('spiralengine_manage_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'spiralengine'));
        }
        
        $this->active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        
        if (!isset($this->tabs[$this->active_tab])) {
            $this->active_tab = 'general';
        }
        ?>
        <div class="wrap spiralengine-settings-wrap">
            <h1>
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('SpiralEngine Settings', 'spiralengine'); ?>
            </h1>
            
            <?php $this->render_tabs(); ?>
            
            <form method="post" action="options.php" id="spiralengine-settings-form">
                <?php settings_fields('spiralengine_settings'); ?>
                
                <div class="spiralengine-settings-container">
                    <?php $this->render_tab_content(); ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render settings tabs
     */
    private function render_tabs() {
        ?>
        <nav class="nav-tab-wrapper spiralengine-settings-tabs">
            <ul>
                <?php foreach ($this->tabs as $key => $tab) : ?>
                    <li class="<?php echo $this->active_tab === $key ? 'active' : ''; ?>">
                        <a href="#" data-tab="<?php echo esc_attr($key); ?>" class="nav-tab">
                            <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                            <?php echo esc_html($tab['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php
    }
    
    /**
     * Render tab content
     */
    private function render_tab_content() {
        $settings = get_option(self::OPTION_NAME, array());
        
        foreach ($this->tabs as $key => $tab) {
            $display = $this->active_tab === $key ? 'block' : 'none';
            ?>
            <div id="spiralengine-settings-<?php echo esc_attr($key); ?>" 
                 class="spiralengine-settings-content" 
                 style="display: <?php echo $display; ?>;">
                <?php
                $method = 'render_' . $key . '_tab';
                if (method_exists($this, $method)) {
                    $this->$method($settings);
                } else {
                    echo '<p>' . __('Tab content not found.', 'spiralengine') . '</p>';
                }
                ?>
            </div>
            <?php
        }
    }
    
    /**
     * Render general settings tab
     */
    private function render_general_tab($settings) {
        ?>
        <h2><?php _e('General Settings', 'spiralengine'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="site_name"><?php _e('Site Name', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="site_name" 
                           name="<?php echo self::OPTION_NAME; ?>[site_name]" 
                           value="<?php echo esc_attr($settings['site_name'] ?? get_bloginfo('name')); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('The name of your mental health platform.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="dashboard_page"><?php _e('Dashboard Page', 'spiralengine'); ?></label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => self::OPTION_NAME . '[dashboard_page]',
                        'id' => 'dashboard_page',
                        'selected' => $settings['dashboard_page'] ?? 0,
                        'show_option_none' => __('— Select —', 'spiralengine'),
                        'option_none_value' => '0'
                    ));
                    ?>
                    <p class="description"><?php _e('The page where the user dashboard is displayed.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="timezone"><?php _e('Default Timezone', 'spiralengine'); ?></label>
                </th>
                <td>
                    <select id="timezone" name="<?php echo self::OPTION_NAME; ?>[timezone]">
                        <?php echo wp_timezone_choice($settings['timezone'] ?? wp_timezone_string()); ?>
                    </select>
                    <p class="description"><?php _e('Default timezone for new users.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Features', 'spiralengine'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[enable_ai]" 
                                   value="1" 
                                   <?php checked(!empty($settings['enable_ai'])); ?> />
                            <?php _e('Enable AI Features', 'spiralengine'); ?>
                        </label>
                        <br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[enable_goals]" 
                                   value="1" 
                                   <?php checked(!empty($settings['enable_goals'])); ?> />
                            <?php _e('Enable Goals System', 'spiralengine'); ?>
                        </label>
                        <br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[enable_exports]" 
                                   value="1" 
                                   <?php checked(!empty($settings['enable_exports'])); ?> />
                            <?php _e('Enable Data Exports', 'spiralengine'); ?>
                        </label>
                        <br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[enable_api]" 
                                   value="1" 
                                   <?php checked(!empty($settings['enable_api'])); ?> />
                            <?php _e('Enable Public API', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="maintenance_mode"><?php _e('Maintenance Mode', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="maintenance_mode"
                               name="<?php echo self::OPTION_NAME; ?>[maintenance_mode]" 
                               value="1" 
                               <?php checked(!empty($settings['maintenance_mode'])); ?> />
                        <?php _e('Enable maintenance mode', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('When enabled, only administrators can access the platform.', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render membership settings tab
     */
    private function render_membership_tab($settings) {
        $membership_settings = $settings['membership'] ?? array();
        ?>
        <h2><?php _e('Membership Settings', 'spiralengine'); ?></h2>
        
        <h3><?php _e('Tier Configuration', 'spiralengine'); ?></h3>
        
        <?php
        $tiers = array(
            'free' => __('Free', 'spiralengine'),
            'bronze' => __('Bronze', 'spiralengine'),
            'silver' => __('Silver', 'spiralengine'),
            'gold' => __('Gold', 'spiralengine'),
            'platinum' => __('Platinum', 'spiralengine')
        );
        
        foreach ($tiers as $tier_key => $tier_name) :
            $tier_settings = $membership_settings[$tier_key] ?? array();
            ?>
            <div class="spiralengine-tier-settings" style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;">
                <h4><?php echo $tier_name; ?> <?php _e('Tier', 'spiralengine'); ?></h4>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('Price', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="<?php echo self::OPTION_NAME; ?>[membership][<?php echo $tier_key; ?>][price]" 
                                   value="<?php echo esc_attr($tier_settings['price'] ?? '0'); ?>" 
                                   min="0" 
                                   step="0.01" 
                                   class="small-text" />
                            <span><?php _e('per month', 'spiralengine'); ?></span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Episode Limit', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="<?php echo self::OPTION_NAME; ?>[membership][<?php echo $tier_key; ?>][episode_limit]" 
                                   value="<?php echo esc_attr($tier_settings['episode_limit'] ?? '-1'); ?>" 
                                   min="-1" 
                                   class="small-text" />
                            <span><?php _e('per month (-1 for unlimited)', 'spiralengine'); ?></span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Widget Access', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="<?php echo self::OPTION_NAME; ?>[membership][<?php echo $tier_key; ?>][widget_limit]" 
                                   value="<?php echo esc_attr($tier_settings['widget_limit'] ?? '-1'); ?>" 
                                   min="-1" 
                                   class="small-text" />
                            <span><?php _e('widgets (-1 for all)', 'spiralengine'); ?></span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('AI Requests', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="<?php echo self::OPTION_NAME; ?>[membership][<?php echo $tier_key; ?>][ai_requests]" 
                                   value="<?php echo esc_attr($tier_settings['ai_requests'] ?? '0'); ?>" 
                                   min="0" 
                                   class="small-text" />
                            <span><?php _e('per month (0 for none)', 'spiralengine'); ?></span>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endforeach; ?>
        
        <h3><?php _e('Stripe Configuration', 'spiralengine'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="stripe_mode"><?php _e('Stripe Mode', 'spiralengine'); ?></label>
                </th>
                <td>
                    <select id="stripe_mode" name="<?php echo self::OPTION_NAME; ?>[stripe_mode]">
                        <option value="test" <?php selected(($settings['stripe_mode'] ?? 'test'), 'test'); ?>>
                            <?php _e('Test Mode', 'spiralengine'); ?>
                        </option>
                        <option value="live" <?php selected(($settings['stripe_mode'] ?? 'test'), 'live'); ?>>
                            <?php _e('Live Mode', 'spiralengine'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="stripe_test_publishable"><?php _e('Test Publishable Key', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="stripe_test_publishable"
                           name="<?php echo self::OPTION_NAME; ?>[stripe_test_publishable]" 
                           value="<?php echo esc_attr($settings['stripe_test_publishable'] ?? ''); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="stripe_test_secret"><?php _e('Test Secret Key', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="stripe_test_secret"
                           name="<?php echo self::OPTION_NAME; ?>[stripe_test_secret]" 
                           value="<?php echo esc_attr($settings['stripe_test_secret'] ?? ''); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="stripe_live_publishable"><?php _e('Live Publishable Key', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="stripe_live_publishable"
                           name="<?php echo self::OPTION_NAME; ?>[stripe_live_publishable]" 
                           value="<?php echo esc_attr($settings['stripe_live_publishable'] ?? ''); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="stripe_live_secret"><?php _e('Live Secret Key', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="stripe_live_secret"
                           name="<?php echo self::OPTION_NAME; ?>[stripe_live_secret]" 
                           value="<?php echo esc_attr($settings['stripe_live_secret'] ?? ''); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row"></th>
                <td>
                    <button type="button" class="button button-secondary spiralengine-test-connection" data-provider="stripe">
                        <?php _e('Test Stripe Connection', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render AI providers tab
     */
    private function render_ai_tab($settings) {
        $ai_settings = $settings['ai'] ?? array();
        ?>
        <h2><?php _e('AI Provider Settings', 'spiralengine'); ?></h2>
        
        <h3><?php _e('OpenAI Configuration', 'spiralengine'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="openai_enabled"><?php _e('Enable OpenAI', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="openai_enabled"
                               name="<?php echo self::OPTION_NAME; ?>[ai][openai][enabled]" 
                               value="1" 
                               <?php checked(!empty($ai_settings['openai']['enabled'])); ?> />
                        <?php _e('Use OpenAI for AI features', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openai_api_key"><?php _e('API Key', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="openai_api_key"
                           name="<?php echo self::OPTION_NAME; ?>[ai][openai][api_key]" 
                           value="<?php echo esc_attr($ai_settings['openai']['api_key'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your OpenAI API key from platform.openai.com', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openai_model"><?php _e('Default Model', 'spiralengine'); ?></label>
                </th>
                <td>
                    <select id="openai_model" name="<?php echo self::OPTION_NAME; ?>[ai][openai][model]">
                        <option value="gpt-4-turbo-preview" <?php selected(($ai_settings['openai']['model'] ?? ''), 'gpt-4-turbo-preview'); ?>>
                            GPT-4 Turbo
                        </option>
                        <option value="gpt-4" <?php selected(($ai_settings['openai']['model'] ?? ''), 'gpt-4'); ?>>
                            GPT-4
                        </option>
                        <option value="gpt-3.5-turbo" <?php selected(($ai_settings['openai']['model'] ?? ''), 'gpt-3.5-turbo'); ?>>
                            GPT-3.5 Turbo
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openai_max_tokens"><?php _e('Max Tokens', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="openai_max_tokens"
                           name="<?php echo self::OPTION_NAME; ?>[ai][openai][max_tokens]" 
                           value="<?php echo esc_attr($ai_settings['openai']['max_tokens'] ?? '1000'); ?>" 
                           min="100" 
                           max="4000" 
                           class="small-text" />
                    <p class="description"><?php _e('Maximum tokens per request (affects cost)', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"></th>
                <td>
                    <button type="button" class="button button-secondary spiralengine-test-connection" data-provider="openai">
                        <?php _e('Test OpenAI Connection', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('AI Usage Limits', 'spiralengine'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ai_rate_limit"><?php _e('Rate Limit', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="ai_rate_limit"
                           name="<?php echo self::OPTION_NAME; ?>[ai][rate_limit]" 
                           value="<?php echo esc_attr($ai_settings['rate_limit'] ?? '60'); ?>" 
                           min="1" 
                           class="small-text" />
                    <span><?php _e('requests per hour per user', 'spiralengine'); ?></span>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ai_cache_duration"><?php _e('Cache Duration', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="ai_cache_duration"
                           name="<?php echo self::OPTION_NAME; ?>[ai][cache_duration]" 
                           value="<?php echo esc_attr($ai_settings['cache_duration'] ?? '24'); ?>" 
                           min="0" 
                           class="small-text" />
                    <span><?php _e('hours (0 to disable caching)', 'spiralengine'); ?></span>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render email settings tab
     */
    private function render_email_tab($settings) {
        $email_settings = $settings['email'] ?? array();
        ?>
        <h2><?php _e('Email Settings', 'spiralengine'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="email_from_name"><?php _e('From Name', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="email_from_name"
                           name="<?php echo self::OPTION_NAME; ?>[email][from_name]" 
                           value="<?php echo esc_attr($email_settings['from_name'] ?? get_bloginfo('name')); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="email_from_address"><?php _e('From Address', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="email" 
                           id="email_from_address"
                           name="<?php echo self::OPTION_NAME; ?>[email][from_address]" 
                           value="<?php echo esc_attr($email_settings['from_address'] ?? get_option('admin_email')); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="email_footer"><?php _e('Email Footer', 'spiralengine'); ?></label>
                </th>
                <td>
                    <textarea id="email_footer"
                              name="<?php echo self::OPTION_NAME; ?>[email][footer]" 
                              rows="3" 
                              class="large-text"><?php echo esc_textarea($email_settings['footer'] ?? ''); ?></textarea>
                    <p class="description"><?php _e('Text to appear at the bottom of all emails.', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Email Templates', 'spiralengine'); ?></h3>
        
        <p><?php _e('Customize email templates sent to users.', 'spiralengine'); ?></p>
        
        <?php
        $templates = array(
            'welcome' => __('Welcome Email', 'spiralengine'),
            'tier_upgrade' => __('Tier Upgrade', 'spiralengine'),
            'payment_failed' => __('Payment Failed', 'spiralengine'),
            'weekly_summary' => __('Weekly Summary', 'spiralengine')
        );
        
        foreach ($templates as $template_key => $template_name) :
            ?>
            <div style="margin: 20px 0;">
                <h4><?php echo $template_name; ?></h4>
                <label>
                    <input type="checkbox" 
                           name="<?php echo self::OPTION_NAME; ?>[email][templates][<?php echo $template_key; ?>][enabled]" 
                           value="1" 
                           <?php checked(!empty($email_settings['templates'][$template_key]['enabled'])); ?> />
                    <?php _e('Enable this email', 'spiralengine'); ?>
                </label>
            </div>
        <?php endforeach; ?>
        <?php
    }
    
    /**
     * Render notifications settings tab
     */
    private function render_notifications_tab($settings) {
        $notification_settings = $settings['notifications'] ?? array();
        ?>
        <h2><?php _e('Notification Settings', 'spiralengine'); ?></h2>
        
        <h3><?php _e('Dashboard Notifications', 'spiralengine'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Notifications', 'spiralengine'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[notifications][streak_milestone]" 
                                   value="1" 
                                   <?php checked(!empty($notification_settings['streak_milestone'])); ?> />
                            <?php _e('Streak milestones', 'spiralengine'); ?>
                        </label>
                        <br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[notifications][goal_completion]" 
                                   value="1" 
                                   <?php checked(!empty($notification_settings['goal_completion'])); ?> />
                            <?php _e('Goal completions', 'spiralengine'); ?>
                        </label>
                        <br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[notifications][pattern_detected]" 
                                   value="1" 
                                   <?php checked(!empty($notification_settings['pattern_detected'])); ?> />
                            <?php _e('Pattern detections', 'spiralengine'); ?>
                        </label>
                        <br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[notifications][tier_upgrade]" 
                                   value="1" 
                                   <?php checked(!empty($notification_settings['tier_upgrade'])); ?> />
                            <?php _e('Tier upgrades available', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_duration"><?php _e('Display Duration', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="notification_duration"
                           name="<?php echo self::OPTION_NAME; ?>[notifications][duration]" 
                           value="<?php echo esc_attr($notification_settings['duration'] ?? '5'); ?>" 
                           min="1" 
                           max="30" 
                           class="small-text" />
                    <span><?php _e('seconds', 'spiralengine'); ?></span>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Alert Thresholds', 'spiralengine'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="alert_high_severity"><?php _e('High Severity Alert', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="alert_high_severity"
                           name="<?php echo self::OPTION_NAME; ?>[notifications][alert_high_severity]" 
                           value="<?php echo esc_attr($notification_settings['alert_high_severity'] ?? '8'); ?>" 
                           min="1" 
                           max="10" 
                           class="small-text" />
                    <span><?php _e('or higher', 'spiralengine'); ?></span>
                    <p class="description"><?php _e('Alert when episode severity reaches this level.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="alert_frequency"><?php _e('Frequency Alert', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="alert_frequency"
                           name="<?php echo self::OPTION_NAME; ?>[notifications][alert_frequency]" 
                           value="<?php echo esc_attr($notification_settings['alert_frequency'] ?? '5'); ?>" 
                           min="1" 
                           class="small-text" />
                    <span><?php _e('episodes per day', 'spiralengine'); ?></span>
                    <p class="description"><?php _e('Alert when daily episode count exceeds this number.', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render API settings tab
     */
    private function render_api_tab($settings) {
        $api_settings = $settings['api'] ?? array();
        ?>
        <h2><?php _e('API Settings', 'spiralengine'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="api_enabled"><?php _e('Enable API', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="api_enabled"
                               name="<?php echo self::OPTION_NAME; ?>[api][enabled]" 
                               value="1" 
                               <?php checked(!empty($api_settings['enabled'])); ?> />
                        <?php _e('Enable public API access', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Allow third-party applications to access SpiralEngine data.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="api_version"><?php _e('API Version', 'spiralengine'); ?></label>
                </th>
                <td>
                    <select id="api_version" name="<?php echo self::OPTION_NAME; ?>[api][version]">
                        <option value="v1" <?php selected(($api_settings['version'] ?? 'v1'), 'v1'); ?>>v1</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="api_rate_limit"><?php _e('Rate Limit', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="api_rate_limit"
                           name="<?php echo self::OPTION_NAME; ?>[api][rate_limit]" 
                           value="<?php echo esc_attr($api_settings['rate_limit'] ?? '1000'); ?>" 
                           min="1" 
                           class="small-text" />
                    <span><?php _e('requests per hour per key', 'spiralengine'); ?></span>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('API Keys', 'spiralengine'); ?></h3>
        
        <p><?php _e('Generate and manage API keys for external applications.', 'spiralengine'); ?></p>
        
        <p>
            <button type="button" class="button button-primary" id="generate-api-key">
                <?php _e('Generate New API Key', 'spiralengine'); ?>
            </button>
        </p>
        
        <?php
        // TODO: Add API key management table
        ?>
        <?php
    }
    
    /**
     * Render advanced settings tab
     */
    private function render_advanced_tab($settings) {
        $advanced_settings = $settings['advanced'] ?? array();
        ?>
        <h2><?php _e('Advanced Settings', 'spiralengine'); ?></h2>
        
        <h3><?php _e('Performance', 'spiralengine'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="cache_duration"><?php _e('Cache Duration', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="cache_duration"
                           name="<?php echo self::OPTION_NAME; ?>[advanced][cache_duration]" 
                           value="<?php echo esc_attr($advanced_settings['cache_duration'] ?? '3600'); ?>" 
                           min="0" 
                           class="small-text" />
                    <span><?php _e('seconds (0 to disable)', 'spiralengine'); ?></span>
                    <p class="description"><?php _e('How long to cache dashboard data.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="batch_size"><?php _e('Batch Processing Size', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="batch_size"
                           name="<?php echo self::OPTION_NAME; ?>[advanced][batch_size]" 
                           value="<?php echo esc_attr($advanced_settings['batch_size'] ?? '50'); ?>" 
                           min="10" 
                           max="500" 
                           class="small-text" />
                    <p class="description"><?php _e('Number of records to process at once.', 'spiralengine'); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Security', 'spiralengine'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="session_timeout"><?php _e('Session Timeout', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="session_timeout"
                           name="<?php echo self::OPTION_NAME; ?>[advanced][session_timeout]" 
                           value="<?php echo esc_attr($advanced_settings['session_timeout'] ?? '1440'); ?>" 
                           min="5" 
                           class="small-text" />
                    <span><?php _e('minutes', 'spiralengine'); ?></span>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ip_whitelist"><?php _e('IP Whitelist', 'spiralengine'); ?></label>
                </th>
                <td>
                    <textarea id="ip_whitelist"
                              name="<?php echo self::OPTION_NAME; ?>[advanced][ip_whitelist]" 
                              rows="3" 
                              class="large-text"><?php echo esc_textarea($advanced_settings['ip_whitelist'] ?? ''); ?></textarea>
                    <p class="description"><?php _e('One IP address per line. Leave empty to allow all.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Security Features', 'spiralengine'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[advanced][force_ssl]" 
                                   value="1" 
                                   <?php checked(!empty($advanced_settings['force_ssl'])); ?> />
                            <?php _e('Force SSL for all pages', 'spiralengine'); ?>
                        </label>
                        <br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[advanced][two_factor]" 
                                   value="1" 
                                   <?php checked(!empty($advanced_settings['two_factor'])); ?> />
                            <?php _e('Enable two-factor authentication', 'spiralengine'); ?>
                        </label>
                        <br />
                        
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo self::OPTION_NAME; ?>[advanced][audit_log]" 
                                   value="1" 
                                   <?php checked(!empty($advanced_settings['audit_log'])); ?> />
                            <?php _e('Enable detailed audit logging', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Data Retention', 'spiralengine'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="data_retention"><?php _e('Episode Data Retention', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="data_retention"
                           name="<?php echo self::OPTION_NAME; ?>[advanced][data_retention]" 
                           value="<?php echo esc_attr($advanced_settings['data_retention'] ?? '0'); ?>" 
                           min="0" 
                           class="small-text" />
                    <span><?php _e('days (0 for indefinite)', 'spiralengine'); ?></span>
                    <p class="description"><?php _e('Automatically delete episodes older than this.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="log_retention"><?php _e('Log Retention', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="log_retention"
                           name="<?php echo self::OPTION_NAME; ?>[advanced][log_retention]" 
                           value="<?php echo esc_attr($advanced_settings['log_retention'] ?? '90'); ?>" 
                           min="1" 
                           class="small-text" />
                    <span><?php _e('days', 'spiralengine'); ?></span>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render tools tab
     */
    private function render_tools_tab($settings) {
        ?>
        <h2><?php _e('Tools', 'spiralengine'); ?></h2>
        
        <div class="spiralengine-tools-section">
            <h3><?php _e('Cache Management', 'spiralengine'); ?></h3>
            
            <p><?php _e('Clear various caches to resolve display issues.', 'spiralengine'); ?></p>
            
            <p>
                <button type="button" class="button button-secondary" id="clear-all-cache">
                    <?php _e('Clear All Caches', 'spiralengine'); ?>
                </button>
                
                <button type="button" class="button button-secondary" id="clear-transients">
                    <?php _e('Clear Transients', 'spiralengine'); ?>
                </button>
                
                <button type="button" class="button button-secondary" id="clear-user-cache">
                    <?php _e('Clear User Cache', 'spiralengine'); ?>
                </button>
            </p>
        </div>
        
        <div class="spiralengine-tools-section">
            <h3><?php _e('Database Tools', 'spiralengine'); ?></h3>
            
            <p><?php _e('Optimize and maintain database tables.', 'spiralengine'); ?></p>
            
            <p>
                <button type="button" class="button button-secondary" id="optimize-tables">
                    <?php _e('Optimize Tables', 'spiralengine'); ?>
                </button>
                
                <button type="button" class="button button-secondary" id="repair-tables">
                    <?php _e('Repair Tables', 'spiralengine'); ?>
                </button>
            </p>
        </div>
        
        <div class="spiralengine-tools-section">
            <h3><?php _e('Diagnostic Information', 'spiralengine'); ?></h3>
            
            <textarea readonly class="large-text" rows="10"><?php echo $this->get_diagnostic_info(); ?></textarea>
            
            <p>
                <button type="button" class="button button-secondary" id="copy-diagnostic">
                    <?php _e('Copy to Clipboard', 'spiralengine'); ?>
                </button>
            </p>
        </div>
        
        <div class="spiralengine-tools-section">
            <h3><?php _e('Reset Options', 'spiralengine'); ?></h3>
            
            <p class="description" style="color: #d63638;">
                <?php _e('Warning: These actions cannot be undone!', 'spiralengine'); ?>
            </p>
            
            <p>
                <button type="button" class="button button-secondary" id="reset-settings">
                    <?php _e('Reset All Settings', 'spiralengine'); ?>
                </button>
                
                <button type="button" class="button button-secondary" id="reset-user-data" style="color: #d63638;">
                    <?php _e('Delete All User Data', 'spiralengine'); ?>
                </button>
            </p>
        </div>
        <?php
    }
    
    /**
     * Get diagnostic information
     *
     * @return string
     */
    private function get_diagnostic_info() {
        global $wpdb;
        
        $info = array();
        
        // Plugin version
        $info[] = 'SpiralEngine Version: ' . SPIRALENGINE_VERSION;
        
        // WordPress version
        $info[] = 'WordPress Version: ' . get_bloginfo('version');
        
        // PHP version
        $info[] = 'PHP Version: ' . phpversion();
        
        // MySQL version
        $info[] = 'MySQL Version: ' . $wpdb->db_version();
        
        // Active theme
        $theme = wp_get_theme();
        $info[] = 'Active Theme: ' . $theme->get('Name') . ' ' . $theme->get('Version');
        
        // Memory limit
        $info[] = 'PHP Memory Limit: ' . ini_get('memory_limit');
        
        // Upload max size
        $info[] = 'Upload Max Size: ' . ini_get('upload_max_filesize');
        
        // Active plugins
        $active_plugins = get_option('active_plugins');
        $info[] = 'Active Plugins: ' . count($active_plugins);
        
        // User count
        $user_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_memberships");
        $info[] = 'Total Users: ' . $user_count;
        
        // Episode count
        $episode_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes");
        $info[] = 'Total Episodes: ' . $episode_count;
        
        return implode("\n", $info);
    }
    
    /**
     * Process admin actions
     */
    public function process_actions() {
        if (!isset($_GET['action']) || !isset($_GET['_wpnonce'])) {
            return;
        }
        
        $action = sanitize_key($_GET['action']);
        $nonce = $_GET['_wpnonce'];
        
        switch ($action) {
            case 'clear_cache':
                if (wp_verify_nonce($nonce, 'spiralengine_clear_cache')) {
                    $this->clear_all_cache();
                    wp_redirect(admin_url('admin.php?page=spiralengine&cache_cleared=1'));
                    exit;
                }
                break;
        }
    }
    
    /**
     * Clear all caches
     */
    private function clear_all_cache() {
        global $wpdb;
        
        // Clear transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_spiralengine%' 
            OR option_name LIKE '_transient_timeout_spiralengine%'"
        );
        
        // Clear object cache
        wp_cache_flush();
        
        // Trigger action for other caches
        do_action('spiralengine_clear_cache');
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('spiralengine_settings');
        
        if (!current_user_can('spiralengine_manage_settings')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'spiralengine')));
        }
        
        // Process and save settings
        $settings = $_POST[self::OPTION_NAME] ?? array();
        $sanitized = $this->sanitize_settings($settings);
        
        update_option(self::OPTION_NAME, $sanitized);
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully.', 'spiralengine')
        ));
    }
    
    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('spiralengine_test_connection', 'nonce');
        
        if (!current_user_can('spiralengine_manage_settings')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'spiralengine')));
        }
        
        $provider = sanitize_key($_POST['provider'] ?? '');
        $settings = get_option(self::OPTION_NAME, array());
        
        switch ($provider) {
            case 'openai':
                $result = $this->test_openai_connection($settings['ai']['openai'] ?? array());
                break;
                
            case 'stripe':
                $result = $this->test_stripe_connection($settings);
                break;
                
            default:
                $result = array(
                    'success' => false,
                    'message' => __('Unknown provider', 'spiralengine')
                );
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Test OpenAI connection
     *
     * @param array $settings
     * @return array
     */
    private function test_openai_connection($settings) {
        if (empty($settings['api_key'])) {
            return array(
                'success' => false,
                'message' => __('API key is required', 'spiralengine')
            );
        }
        
        // Test with a simple completion
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $settings['model'] ?? 'gpt-3.5-turbo',
                'messages' => array(
                    array('role' => 'user', 'content' => 'Say "test successful"')
                ),
                'max_tokens' => 10
            )),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return array(
                'success' => false,
                'message' => $body['error']['message'] ?? __('Connection failed', 'spiralengine')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('OpenAI connection successful!', 'spiralengine')
        );
    }
    
    /**
     * Test Stripe connection
     *
     * @param array $settings
     * @return array
     */
    private function test_stripe_connection($settings) {
        $mode = $settings['stripe_mode'] ?? 'test';
        $key = $mode === 'live' 
            ? ($settings['stripe_live_secret'] ?? '')
            : ($settings['stripe_test_secret'] ?? '');
        
        if (empty($key)) {
            return array(
                'success' => false,
                'message' => __('Secret key is required', 'spiralengine')
            );
        }
        
        // Test with account retrieval
        $response = wp_remote_get('https://api.stripe.com/v1/account', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $key
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return array(
                'success' => false,
                'message' => $body['error']['message'] ?? __('Connection failed', 'spiralengine')
            );
        }
        
        return array(
            'success' => true,
            'message' => sprintf(__('Stripe %s mode connection successful!', 'spiralengine'), $mode)
        );
    }
    
    /**
     * Sanitize settings
     *
     * @param array $input
     * @return array
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // General settings
        $sanitized['site_name'] = sanitize_text_field($input['site_name'] ?? '');
        $sanitized['dashboard_page'] = absint($input['dashboard_page'] ?? 0);
        $sanitized['timezone'] = sanitize_text_field($input['timezone'] ?? '');
        $sanitized['enable_ai'] = !empty($input['enable_ai']);
        $sanitized['enable_goals'] = !empty($input['enable_goals']);
        $sanitized['enable_exports'] = !empty($input['enable_exports']);
        $sanitized['enable_api'] = !empty($input['enable_api']);
        $sanitized['maintenance_mode'] = !empty($input['maintenance_mode']);
        
        // Membership settings
        if (isset($input['membership'])) {
            foreach ($input['membership'] as $tier => $tier_settings) {
                $sanitized['membership'][$tier] = array(
                    'price' => floatval($tier_settings['price'] ?? 0),
                    'episode_limit' => intval($tier_settings['episode_limit'] ?? -1),
                    'widget_limit' => intval($tier_settings['widget_limit'] ?? -1),
                    'ai_requests' => intval($tier_settings['ai_requests'] ?? 0)
                );
            }
        }
        
        // Stripe settings
        $sanitized['stripe_mode'] = in_array($input['stripe_mode'] ?? 'test', array('test', 'live')) 
            ? $input['stripe_mode'] : 'test';
        $sanitized['stripe_test_publishable'] = sanitize_text_field($input['stripe_test_publishable'] ?? '');
        $sanitized['stripe_test_secret'] = sanitize_text_field($input['stripe_test_secret'] ?? '');
        $sanitized['stripe_live_publishable'] = sanitize_text_field($input['stripe_live_publishable'] ?? '');
        $sanitized['stripe_live_secret'] = sanitize_text_field($input['stripe_live_secret'] ?? '');
        
        // AI settings
        if (isset($input['ai'])) {
            $sanitized['ai'] = array(
                'openai' => array(
                    'enabled' => !empty($input['ai']['openai']['enabled']),
                    'api_key' => sanitize_text_field($input['ai']['openai']['api_key'] ?? ''),
                    'model' => sanitize_text_field($input['ai']['openai']['model'] ?? 'gpt-3.5-turbo'),
                    'max_tokens' => intval($input['ai']['openai']['max_tokens'] ?? 1000)
                ),
                'rate_limit' => intval($input['ai']['rate_limit'] ?? 60),
                'cache_duration' => intval($input['ai']['cache_duration'] ?? 24)
            );
        }
        
        // Email settings
        if (isset($input['email'])) {
            $sanitized['email'] = array(
                'from_name' => sanitize_text_field($input['email']['from_name'] ?? ''),
                'from_address' => sanitize_email($input['email']['from_address'] ?? ''),
                'footer' => wp_kses_post($input['email']['footer'] ?? ''),
                'templates' => array()
            );
            
            if (isset($input['email']['templates'])) {
                foreach ($input['email']['templates'] as $template => $settings) {
                    $sanitized['email']['templates'][$template]['enabled'] = !empty($settings['enabled']);
                }
            }
        }
        
        // Notification settings
        if (isset($input['notifications'])) {
            $sanitized['notifications'] = array(
                'streak_milestone' => !empty($input['notifications']['streak_milestone']),
                'goal_completion' => !empty($input['notifications']['goal_completion']),
                'pattern_detected' => !empty($input['notifications']['pattern_detected']),
                'tier_upgrade' => !empty($input['notifications']['tier_upgrade']),
                'duration' => intval($input['notifications']['duration'] ?? 5),
                'alert_high_severity' => intval($input['notifications']['alert_high_severity'] ?? 8),
                'alert_frequency' => intval($input['notifications']['alert_frequency'] ?? 5)
            );
        }
        
        // API settings
        if (isset($input['api'])) {
            $sanitized['api'] = array(
                'enabled' => !empty($input['api']['enabled']),
                'version' => sanitize_text_field($input['api']['version'] ?? 'v1'),
                'rate_limit' => intval($input['api']['rate_limit'] ?? 1000)
            );
        }
        
        // Advanced settings
        if (isset($input['advanced'])) {
            $sanitized['advanced'] = array(
                'cache_duration' => intval($input['advanced']['cache_duration'] ?? 3600),
                'batch_size' => intval($input['advanced']['batch_size'] ?? 50),
                'session_timeout' => intval($input['advanced']['session_timeout'] ?? 1440),
                'ip_whitelist' => sanitize_textarea_field($input['advanced']['ip_whitelist'] ?? ''),
                'force_ssl' => !empty($input['advanced']['force_ssl']),
                'two_factor' => !empty($input['advanced']['two_factor']),
                'audit_log' => !empty($input['advanced']['audit_log']),
                'data_retention' => intval($input['advanced']['data_retention'] ?? 0),
                'log_retention' => intval($input['advanced']['log_retention'] ?? 90)
            );
        }
        
        return apply_filters('spiralengine_sanitize_settings', $sanitized, $input);
    }
}

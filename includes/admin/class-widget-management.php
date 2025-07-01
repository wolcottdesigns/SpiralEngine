<?php
/**
 * SpiralEngine Widget Management Interface
 *
 * @package    SpiralEngine
 * @subpackage Admin
 * @file       includes/admin/class-widget-management.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SpiralEngine_Widget_Management
 *
 * Manages widget configuration and customization
 */
class SpiralEngine_Widget_Management {
    
    /**
     * Instance of this class
     *
     * @var SpiralEngine_Widget_Management
     */
    private static $instance = null;
    
    /**
     * Available widgets
     *
     * @var array
     */
    private $widgets = array();
    
    /**
     * Widget categories
     *
     * @var array
     */
    private $categories = array();
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Get instance
     *
     * @return SpiralEngine_Widget_Management
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize widget management
     */
    public function init() {
        $this->load_widgets();
        $this->setup_categories();
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_spiralengine_save_widget', array($this, 'ajax_save_widget'));
        add_action('wp_ajax_spiralengine_toggle_widget', array($this, 'ajax_toggle_widget'));
        add_action('wp_ajax_spiralengine_reset_widget', array($this, 'ajax_reset_widget'));
        add_action('wp_ajax_spiralengine_preview_widget', array($this, 'ajax_preview_widget'));
        add_action('wp_ajax_spiralengine_reorder_widgets', array($this, 'ajax_reorder_widgets'));
        add_action('wp_ajax_spiralengine_duplicate_widget', array($this, 'ajax_duplicate_widget'));
    }
    
    /**
     * Load available widgets
     */
    private function load_widgets() {
        $this->widgets = array(
            'mood_tracker' => array(
                'id' => 'mood_tracker',
                'name' => __('Mood Tracker', 'spiralengine'),
                'description' => __('Track daily moods and emotional patterns', 'spiralengine'),
                'category' => 'tracking',
                'icon' => 'dashicons-smiley',
                'settings' => array(
                    'scale_type' => 'numeric',
                    'scale_max' => 10,
                    'show_history' => true,
                    'enable_notes' => true
                ),
                'tier_requirement' => 'free'
            ),
            'thought_diary' => array(
                'id' => 'thought_diary',
                'name' => __('Thought Diary', 'spiralengine'),
                'description' => __('Record and analyze thoughts and feelings', 'spiralengine'),
                'category' => 'journaling',
                'icon' => 'dashicons-edit-page',
                'settings' => array(
                    'prompts_enabled' => true,
                    'auto_save' => true,
                    'word_limit' => 500,
                    'enable_tags' => true
                ),
                'tier_requirement' => 'free'
            ),
            'gratitude_journal' => array(
                'id' => 'gratitude_journal',
                'name' => __('Gratitude Journal', 'spiralengine'),
                'description' => __('Daily gratitude practice and reflection', 'spiralengine'),
                'category' => 'wellness',
                'icon' => 'dashicons-heart',
                'settings' => array(
                    'entries_per_day' => 3,
                    'reminder_enabled' => true,
                    'reminder_time' => '20:00',
                    'streak_tracking' => true
                ),
                'tier_requirement' => 'free'
            ),
            'meditation_timer' => array(
                'id' => 'meditation_timer',
                'name' => __('Meditation Timer', 'spiralengine'),
                'description' => __('Guided meditation and mindfulness timer', 'spiralengine'),
                'category' => 'wellness',
                'icon' => 'dashicons-clock',
                'settings' => array(
                    'default_duration' => 10,
                    'ambient_sounds' => true,
                    'interval_bells' => true,
                    'guided_options' => array()
                ),
                'tier_requirement' => 'bronze'
            ),
            'coping_strategies' => array(
                'id' => 'coping_strategies',
                'name' => __('Coping Strategies', 'spiralengine'),
                'description' => __('Personalized coping techniques toolkit', 'spiralengine'),
                'category' => 'skills',
                'icon' => 'dashicons-lightbulb',
                'settings' => array(
                    'categories' => array('anxiety', 'depression', 'stress'),
                    'favorites_enabled' => true,
                    'custom_strategies' => true,
                    'effectiveness_tracking' => true
                ),
                'tier_requirement' => 'bronze'
            ),
            'goal_tracker' => array(
                'id' => 'goal_tracker',
                'name' => __('Goal Tracker', 'spiralengine'),
                'description' => __('Set and track personal goals', 'spiralengine'),
                'category' => 'productivity',
                'icon' => 'dashicons-flag',
                'settings' => array(
                    'goal_types' => array('daily', 'weekly', 'monthly'),
                    'progress_visualization' => true,
                    'milestone_celebrations' => true,
                    'accountability_partners' => false
                ),
                'tier_requirement' => 'silver'
            ),
            'symptom_tracker' => array(
                'id' => 'symptom_tracker',
                'name' => __('Symptom Tracker', 'spiralengine'),
                'description' => __('Monitor physical and mental health symptoms', 'spiralengine'),
                'category' => 'health',
                'icon' => 'dashicons-chart-line',
                'settings' => array(
                    'symptom_categories' => array(),
                    'severity_scale' => 10,
                    'medication_tracking' => true,
                    'export_enabled' => true
                ),
                'tier_requirement' => 'silver'
            ),
            'ai_chat' => array(
                'id' => 'ai_chat',
                'name' => __('AI Wellness Chat', 'spiralengine'),
                'description' => __('AI-powered mental health support chat', 'spiralengine'),
                'category' => 'ai',
                'icon' => 'dashicons-format-chat',
                'settings' => array(
                    'personality' => 'supportive',
                    'response_style' => 'empathetic',
                    'session_memory' => true,
                    'crisis_detection' => true
                ),
                'tier_requirement' => 'gold'
            ),
            'insights_dashboard' => array(
                'id' => 'insights_dashboard',
                'name' => __('Insights Dashboard', 'spiralengine'),
                'description' => __('Advanced analytics and pattern recognition', 'spiralengine'),
                'category' => 'analytics',
                'icon' => 'dashicons-chart-area',
                'settings' => array(
                    'time_ranges' => array('week', 'month', 'year'),
                    'correlation_analysis' => true,
                    'predictive_insights' => true,
                    'export_reports' => true
                ),
                'tier_requirement' => 'platinum'
            ),
            'crisis_toolkit' => array(
                'id' => 'crisis_toolkit',
                'name' => __('Crisis Support Toolkit', 'spiralengine'),
                'description' => __('Emergency resources and crisis management', 'spiralengine'),
                'category' => 'support',
                'icon' => 'dashicons-sos',
                'settings' => array(
                    'emergency_contacts' => array(),
                    'safety_plan' => true,
                    'grounding_exercises' => true,
                    'hotline_numbers' => true
                ),
                'tier_requirement' => 'free'
            )
        );
        
        // Allow filtering of widgets
        $this->widgets = apply_filters('spiralengine_available_widgets', $this->widgets);
    }
    
    /**
     * Setup widget categories
     */
    private function setup_categories() {
        $this->categories = array(
            'tracking' => array(
                'name' => __('Tracking', 'spiralengine'),
                'description' => __('Monitor your mental health journey', 'spiralengine'),
                'icon' => 'dashicons-chart-line'
            ),
            'journaling' => array(
                'name' => __('Journaling', 'spiralengine'),
                'description' => __('Express thoughts and feelings', 'spiralengine'),
                'icon' => 'dashicons-edit'
            ),
            'wellness' => array(
                'name' => __('Wellness', 'spiralengine'),
                'description' => __('Mindfulness and self-care tools', 'spiralengine'),
                'icon' => 'dashicons-heart'
            ),
            'skills' => array(
                'name' => __('Skills', 'spiralengine'),
                'description' => __('Build coping and life skills', 'spiralengine'),
                'icon' => 'dashicons-awards'
            ),
            'productivity' => array(
                'name' => __('Productivity', 'spiralengine'),
                'description' => __('Achieve your goals', 'spiralengine'),
                'icon' => 'dashicons-performance'
            ),
            'health' => array(
                'name' => __('Health', 'spiralengine'),
                'description' => __('Medical and symptom tracking', 'spiralengine'),
                'icon' => 'dashicons-plus-alt'
            ),
            'ai' => array(
                'name' => __('AI Features', 'spiralengine'),
                'description' => __('Artificial intelligence tools', 'spiralengine'),
                'icon' => 'dashicons-lightbulb'
            ),
            'analytics' => array(
                'name' => __('Analytics', 'spiralengine'),
                'description' => __('Data insights and patterns', 'spiralengine'),
                'icon' => 'dashicons-analytics'
            ),
            'support' => array(
                'name' => __('Support', 'spiralengine'),
                'description' => __('Help and crisis resources', 'spiralengine'),
                'icon' => 'dashicons-groups'
            )
        );
    }
    
    /**
     * Enqueue scripts and styles
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'spiralengine-widgets') === false) {
            return;
        }
        
        wp_enqueue_style('spiralengine-admin');
        wp_enqueue_script('spiralengine-admin');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_media();
        
        // Color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Add widget management specific script
        wp_add_inline_script('spiralengine-admin', $this->get_inline_script());
        
        // Localize script
        wp_localize_script('spiralengine-admin', 'spiralengine_widgets', array(
            'nonce' => wp_create_nonce('spiralengine_widget_management'),
            'strings' => array(
                'save_success' => __('Widget settings saved successfully.', 'spiralengine'),
                'save_error' => __('Error saving widget settings.', 'spiralengine'),
                'reset_confirm' => __('Are you sure you want to reset this widget to default settings?', 'spiralengine'),
                'preview_title' => __('Widget Preview', 'spiralengine'),
                'saving' => __('Saving...', 'spiralengine'),
                'saved' => __('Saved', 'spiralengine')
            )
        ));
    }
    
    /**
     * Get inline JavaScript
     *
     * @return string
     */
    private function get_inline_script() {
        return "
        jQuery(document).ready(function($) {
            // Initialize color pickers
            $('.spiralengine-color-picker').wpColorPicker();
            
            // Widget card interactions
            $('.widget-card').on('click', '.widget-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var button = $(this);
                var widgetId = button.data('widget-id');
                var currentState = button.hasClass('active');
                
                button.prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_toggle_widget',
                        widget_id: widgetId,
                        enabled: !currentState,
                        nonce: spiralengine_widgets.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            button.toggleClass('active');
                            button.find('.toggle-text').text(
                                button.hasClass('active') ? 'Enabled' : 'Disabled'
                            );
                            
                            // Update widget card state
                            button.closest('.widget-card').toggleClass('widget-disabled');
                        } else {
                            alert(response.data.message || spiralengine_widgets.strings.save_error);
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            });
            
            // Widget settings
            $('.widget-configure').on('click', function(e) {
                e.preventDefault();
                var widgetId = $(this).data('widget-id');
                $('#widget-settings-' + widgetId).dialog({
                    title: $(this).data('widget-name') + ' Settings',
                    width: 600,
                    modal: true,
                    buttons: {
                        'Save': function() {
                            var dialog = $(this);
                            var form = dialog.find('form');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: form.serialize() + '&action=spiralengine_save_widget&nonce=' + spiralengine_widgets.nonce,
                                success: function(response) {
                                    if (response.success) {
                                        dialog.dialog('close');
                                        
                                        // Show success message
                                        $('<div class=\"notice notice-success is-dismissible\"><p>' + 
                                          spiralengine_widgets.strings.save_success + '</p></div>')
                                            .insertAfter('.wp-header-end').delay(3000).fadeOut();
                                    } else {
                                        alert(response.data.message || spiralengine_widgets.strings.save_error);
                                    }
                                }
                            });
                        },
                        'Cancel': function() {
                            $(this).dialog('close');
                        }
                    }
                });
            });
            
            // Preview widget
            $('.widget-preview').on('click', function(e) {
                e.preventDefault();
                var widgetId = $(this).data('widget-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_preview_widget',
                        widget_id: widgetId,
                        nonce: spiralengine_widgets.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('<div>' + response.data.html + '</div>').dialog({
                                title: spiralengine_widgets.strings.preview_title,
                                width: 800,
                                height: 600,
                                modal: true,
                                buttons: {
                                    'Close': function() {
                                        $(this).dialog('close');
                                    }
                                }
                            });
                        }
                    }
                });
            });
            
            // Widget sorting
            $('.spiralengine-widgets-grid').sortable({
                items: '.widget-card',
                handle: '.widget-header',
                placeholder: 'widget-placeholder',
                update: function(event, ui) {
                    var order = [];
                    $('.widget-card').each(function(index) {
                        order.push({
                            id: $(this).data('widget-id'),
                            position: index
                        });
                    });
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'spiralengine_reorder_widgets',
                            order: order,
                            nonce: spiralengine_widgets.nonce
                        }
                    });
                }
            });
            
            // Category filtering
            $('.category-filter').on('click', function(e) {
                e.preventDefault();
                var category = $(this).data('category');
                
                $('.category-filter').removeClass('active');
                $(this).addClass('active');
                
                if (category === 'all') {
                    $('.widget-card').show();
                } else {
                    $('.widget-card').hide();
                    $('.widget-card[data-category=\"' + category + '\"]').show();
                }
            });
            
            // Search widgets
            $('#widget-search').on('keyup', function() {
                var searchTerm = $(this).val().toLowerCase();
                
                $('.widget-card').each(function() {
                    var card = $(this);
                    var name = card.find('.widget-name').text().toLowerCase();
                    var description = card.find('.widget-description').text().toLowerCase();
                    
                    if (name.includes(searchTerm) || description.includes(searchTerm)) {
                        card.show();
                    } else {
                        card.hide();
                    }
                });
            });
            
            // Duplicate widget
            $('.widget-duplicate').on('click', function(e) {
                e.preventDefault();
                var widgetId = $(this).data('widget-id');
                
                if (confirm('Create a duplicate of this widget?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'spiralengine_duplicate_widget',
                            widget_id: widgetId,
                            nonce: spiralengine_widgets.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            }
                        }
                    });
                }
            });
            
            // Reset widget settings
            $('.widget-reset').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm(spiralengine_widgets.strings.reset_confirm)) {
                    return;
                }
                
                var widgetId = $(this).data('widget-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_reset_widget',
                        widget_id: widgetId,
                        nonce: spiralengine_widgets.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    }
                });
            });
            
            // Tier restriction notices
            $('.tier-upgrade-link').on('click', function(e) {
                e.preventDefault();
                var requiredTier = $(this).data('required-tier');
                
                $('<div><p>This widget requires the <strong>' + requiredTier + '</strong> tier or higher.</p>' +
                  '<p>Would you like to learn more about upgrading your account?</p></div>').dialog({
                    title: 'Upgrade Required',
                    modal: true,
                    buttons: {
                        'View Plans': function() {
                            window.location.href = '/pricing/';
                        },
                        'Cancel': function() {
                            $(this).dialog('close');
                        }
                    }
                });
            });
        });
        ";
    }
    
    /**
     * Render widget management page
     */
    public function render_page() {
        if (!current_user_can('spiralengine_manage_widgets')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'spiralengine'));
        }
        
        $active_category = isset($_GET['category']) ? sanitize_key($_GET['category']) : 'all';
        
        ?>
        <div class="wrap spiralengine-widgets-wrap">
            <h1>
                <span class="dashicons dashicons-screenoptions"></span>
                <?php _e('Widget Management', 'spiralengine'); ?>
            </h1>
            
            <div class="spiralengine-widgets-header">
                <div class="widget-search-box">
                    <input type="text" 
                           id="widget-search" 
                           placeholder="<?php esc_attr_e('Search widgets...', 'spiralengine'); ?>" 
                           class="regular-text" />
                </div>
                
                <div class="widget-view-options">
                    <button class="button view-grid active" data-view="grid">
                        <span class="dashicons dashicons-grid-view"></span>
                    </button>
                    <button class="button view-list" data-view="list">
                        <span class="dashicons dashicons-list-view"></span>
                    </button>
                </div>
            </div>
            
            <div class="spiralengine-widget-categories">
                <a href="#" class="category-filter <?php echo $active_category === 'all' ? 'active' : ''; ?>" data-category="all">
                    <?php _e('All Widgets', 'spiralengine'); ?>
                </a>
                
                <?php foreach ($this->categories as $cat_id => $category) : ?>
                    <a href="#" 
                       class="category-filter <?php echo $active_category === $cat_id ? 'active' : ''; ?>" 
                       data-category="<?php echo esc_attr($cat_id); ?>">
                        <span class="dashicons <?php echo esc_attr($category['icon']); ?>"></span>
                        <?php echo esc_html($category['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <div class="spiralengine-widgets-grid">
                <?php $this->render_widget_cards(); ?>
            </div>
            
            <?php $this->render_widget_settings_dialogs(); ?>
        </div>
        <?php
    }
    
    /**
     * Render widget cards
     */
    private function render_widget_cards() {
        $widget_settings = get_option('spiralengine_widget_settings', array());
        $enabled_widgets = get_option('spiralengine_enabled_widgets', array());
        
        foreach ($this->widgets as $widget_id => $widget) :
            $is_enabled = in_array($widget_id, $enabled_widgets);
            $user_tier = SpiralEngine_Membership::get_user_tier(get_current_user_id());
            $can_access = $this->user_can_access_widget($widget_id, $user_tier);
            ?>
            <div class="widget-card <?php echo !$is_enabled ? 'widget-disabled' : ''; ?> <?php echo !$can_access ? 'widget-locked' : ''; ?>" 
                 data-widget-id="<?php echo esc_attr($widget_id); ?>"
                 data-category="<?php echo esc_attr($widget['category']); ?>">
                
                <div class="widget-header">
                    <span class="dashicons <?php echo esc_attr($widget['icon']); ?> widget-icon"></span>
                    <h3 class="widget-name"><?php echo esc_html($widget['name']); ?></h3>
                    
                    <?php if ($widget['tier_requirement'] !== 'free') : ?>
                        <span class="tier-badge tier-<?php echo esc_attr($widget['tier_requirement']); ?>">
                            <?php echo ucfirst($widget['tier_requirement']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="widget-body">
                    <p class="widget-description"><?php echo esc_html($widget['description']); ?></p>
                    
                    <?php if ($can_access) : ?>
                        <div class="widget-stats">
                            <?php
                            $usage_stats = $this->get_widget_usage_stats($widget_id);
                            ?>
                            <span class="stat">
                                <strong><?php echo number_format($usage_stats['total_users']); ?></strong>
                                <?php _e('Active Users', 'spiralengine'); ?>
                            </span>
                            <span class="stat">
                                <strong><?php echo number_format($usage_stats['total_episodes']); ?></strong>
                                <?php _e('Episodes', 'spiralengine'); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="widget-footer">
                    <?php if ($can_access) : ?>
                        <button class="button widget-toggle <?php echo $is_enabled ? 'active' : ''; ?>" 
                                data-widget-id="<?php echo esc_attr($widget_id); ?>">
                            <span class="dashicons <?php echo $is_enabled ? 'dashicons-yes' : 'dashicons-no-alt'; ?>"></span>
                            <span class="toggle-text"><?php echo $is_enabled ? __('Enabled', 'spiralengine') : __('Disabled', 'spiralengine'); ?></span>
                        </button>
                        
                        <div class="widget-actions">
                            <button class="button widget-configure" 
                                    data-widget-id="<?php echo esc_attr($widget_id); ?>"
                                    data-widget-name="<?php echo esc_attr($widget['name']); ?>">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php _e('Settings', 'spiralengine'); ?>
                            </button>
                            
                            <button class="button widget-preview" 
                                    data-widget-id="<?php echo esc_attr($widget_id); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php _e('Preview', 'spiralengine'); ?>
                            </button>
                            
                            <div class="widget-more-actions">
                                <button class="button widget-more">
                                    <span class="dashicons dashicons-ellipsis"></span>
                                </button>
                                <div class="more-actions-menu">
                                    <a href="#" class="widget-duplicate" data-widget-id="<?php echo esc_attr($widget_id); ?>">
                                        <?php _e('Duplicate', 'spiralengine'); ?>
                                    </a>
                                    <a href="#" class="widget-reset" data-widget-id="<?php echo esc_attr($widget_id); ?>">
                                        <?php _e('Reset Settings', 'spiralengine'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="widget-locked-message">
                            <p>
                                <?php
                                printf(
                                    __('Requires %s tier or higher', 'spiralengine'),
                                    '<strong>' . ucfirst($widget['tier_requirement']) . '</strong>'
                                );
                                ?>
                            </p>
                            <a href="#" class="tier-upgrade-link" data-required-tier="<?php echo esc_attr($widget['tier_requirement']); ?>">
                                <?php _e('Upgrade Account', 'spiralengine'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach;
    }
    
    /**
     * Render widget settings dialogs
     */
    private function render_widget_settings_dialogs() {
        $widget_settings = get_option('spiralengine_widget_settings', array());
        
        foreach ($this->widgets as $widget_id => $widget) :
            $settings = isset($widget_settings[$widget_id]) 
                ? wp_parse_args($widget_settings[$widget_id], $widget['settings']) 
                : $widget['settings'];
            ?>
            <div id="widget-settings-<?php echo esc_attr($widget_id); ?>" style="display: none;">
                <form class="widget-settings-form">
                    <input type="hidden" name="widget_id" value="<?php echo esc_attr($widget_id); ?>" />
                    
                    <?php
                    // Render settings based on widget type
                    $this->render_widget_settings($widget_id, $widget, $settings);
                    ?>
                </form>
            </div>
        <?php endforeach;
    }
    
    /**
     * Render widget-specific settings
     *
     * @param string $widget_id
     * @param array $widget
     * @param array $settings
     */
    private function render_widget_settings($widget_id, $widget, $settings) {
        switch ($widget_id) {
            case 'mood_tracker':
                $this->render_mood_tracker_settings($settings);
                break;
                
            case 'thought_diary':
                $this->render_thought_diary_settings($settings);
                break;
                
            case 'gratitude_journal':
                $this->render_gratitude_journal_settings($settings);
                break;
                
            case 'meditation_timer':
                $this->render_meditation_timer_settings($settings);
                break;
                
            case 'coping_strategies':
                $this->render_coping_strategies_settings($settings);
                break;
                
            case 'goal_tracker':
                $this->render_goal_tracker_settings($settings);
                break;
                
            case 'symptom_tracker':
                $this->render_symptom_tracker_settings($settings);
                break;
                
            case 'ai_chat':
                $this->render_ai_chat_settings($settings);
                break;
                
            case 'insights_dashboard':
                $this->render_insights_dashboard_settings($settings);
                break;
                
            case 'crisis_toolkit':
                $this->render_crisis_toolkit_settings($settings);
                break;
                
            default:
                $this->render_generic_settings($widget_id, $settings);
                break;
        }
    }
    
    /**
     * Render mood tracker settings
     *
     * @param array $settings
     */
    private function render_mood_tracker_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="mood_scale_type"><?php _e('Scale Type', 'spiralengine'); ?></label>
                </th>
                <td>
                    <select id="mood_scale_type" name="settings[scale_type]">
                        <option value="numeric" <?php selected($settings['scale_type'], 'numeric'); ?>>
                            <?php _e('Numeric (1-10)', 'spiralengine'); ?>
                        </option>
                        <option value="emoji" <?php selected($settings['scale_type'], 'emoji'); ?>>
                            <?php _e('Emoji Scale', 'spiralengine'); ?>
                        </option>
                        <option value="words" <?php selected($settings['scale_type'], 'words'); ?>>
                            <?php _e('Word Scale', 'spiralengine'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="mood_scale_max"><?php _e('Maximum Scale Value', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="mood_scale_max" 
                           name="settings[scale_max]" 
                           value="<?php echo esc_attr($settings['scale_max']); ?>" 
                           min="5" 
                           max="10" />
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Features', 'spiralengine'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[show_history]" 
                               value="1" 
                               <?php checked($settings['show_history']); ?> />
                        <?php _e('Show mood history graph', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[enable_notes]" 
                               value="1" 
                               <?php checked($settings['enable_notes']); ?> />
                        <?php _e('Enable mood notes', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e('Mood Labels', 'spiralengine'); ?></label>
                </th>
                <td>
                    <p class="description"><?php _e('Customize the labels for each mood level', 'spiralengine'); ?></p>
                    <?php for ($i = 1; $i <= $settings['scale_max']; $i++) : ?>
                        <div style="margin: 5px 0;">
                            <label>
                                <?php echo $i; ?>: 
                                <input type="text" 
                                       name="settings[mood_labels][<?php echo $i; ?>]" 
                                       value="<?php echo esc_attr($settings['mood_labels'][$i] ?? ''); ?>" 
                                       placeholder="<?php esc_attr_e('Label', 'spiralengine'); ?>" />
                            </label>
                        </div>
                    <?php endfor; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render thought diary settings
     *
     * @param array $settings
     */
    private function render_thought_diary_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="diary_word_limit"><?php _e('Word Limit', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="diary_word_limit" 
                           name="settings[word_limit]" 
                           value="<?php echo esc_attr($settings['word_limit']); ?>" 
                           min="0" 
                           step="50" />
                    <p class="description"><?php _e('0 for no limit', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Features', 'spiralengine'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[prompts_enabled]" 
                               value="1" 
                               <?php checked($settings['prompts_enabled']); ?> />
                        <?php _e('Show writing prompts', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[auto_save]" 
                               value="1" 
                               <?php checked($settings['auto_save']); ?> />
                        <?php _e('Auto-save drafts', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[enable_tags]" 
                               value="1" 
                               <?php checked($settings['enable_tags']); ?> />
                        <?php _e('Enable entry tagging', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e('Writing Prompts', 'spiralengine'); ?></label>
                </th>
                <td>
                    <textarea name="settings[custom_prompts]" 
                              rows="5" 
                              class="large-text"
                              placeholder="<?php esc_attr_e('Enter one prompt per line', 'spiralengine'); ?>"><?php 
                        echo esc_textarea(implode("\n", $settings['custom_prompts'] ?? array())); 
                    ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render gratitude journal settings
     *
     * @param array $settings
     */
    private function render_gratitude_journal_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="gratitude_entries"><?php _e('Entries per Day', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="gratitude_entries" 
                           name="settings[entries_per_day]" 
                           value="<?php echo esc_attr($settings['entries_per_day']); ?>" 
                           min="1" 
                           max="10" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="reminder_enabled"><?php _e('Daily Reminder', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="reminder_enabled"
                               name="settings[reminder_enabled]" 
                               value="1" 
                               <?php checked($settings['reminder_enabled']); ?> />
                        <?php _e('Enable daily reminder', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="reminder_time"><?php _e('Reminder Time', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="time" 
                           id="reminder_time" 
                           name="settings[reminder_time]" 
                           value="<?php echo esc_attr($settings['reminder_time']); ?>" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e('Streak Tracking', 'spiralengine'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[streak_tracking]" 
                               value="1" 
                               <?php checked($settings['streak_tracking']); ?> />
                        <?php _e('Track daily streaks', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render meditation timer settings
     *
     * @param array $settings
     */
    private function render_meditation_timer_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="default_duration"><?php _e('Default Duration', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="default_duration" 
                           name="settings[default_duration]" 
                           value="<?php echo esc_attr($settings['default_duration']); ?>" 
                           min="1" 
                           max="60" />
                    <span><?php _e('minutes', 'spiralengine'); ?></span>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Audio Features', 'spiralengine'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[ambient_sounds]" 
                               value="1" 
                               <?php checked($settings['ambient_sounds']); ?> />
                        <?php _e('Enable ambient sounds', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[interval_bells]" 
                               value="1" 
                               <?php checked($settings['interval_bells']); ?> />
                        <?php _e('Interval bells', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e('Ambient Sounds', 'spiralengine'); ?></label>
                </th>
                <td>
                    <div class="sound-options">
                        <?php
                        $sounds = array(
                            'rain' => __('Rain', 'spiralengine'),
                            'ocean' => __('Ocean Waves', 'spiralengine'),
                            'forest' => __('Forest', 'spiralengine'),
                            'white_noise' => __('White Noise', 'spiralengine'),
                            'singing_bowl' => __('Singing Bowl', 'spiralengine')
                        );
                        
                        foreach ($sounds as $sound_id => $sound_name) :
                            ?>
                            <label style="display: block; margin: 5px 0;">
                                <input type="checkbox" 
                                       name="settings[available_sounds][]" 
                                       value="<?php echo esc_attr($sound_id); ?>" 
                                       <?php checked(in_array($sound_id, $settings['available_sounds'] ?? array())); ?> />
                                <?php echo esc_html($sound_name); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render coping strategies settings
     *
     * @param array $settings
     */
    private function render_coping_strategies_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php _e('Strategy Categories', 'spiralengine'); ?></label>
                </th>
                <td>
                    <?php
                    $categories = array(
                        'anxiety' => __('Anxiety', 'spiralengine'),
                        'depression' => __('Depression', 'spiralengine'),
                        'stress' => __('Stress', 'spiralengine'),
                        'anger' => __('Anger', 'spiralengine'),
                        'panic' => __('Panic', 'spiralengine'),
                        'sleep' => __('Sleep Issues', 'spiralengine')
                    );
                    
                    foreach ($categories as $cat_id => $cat_name) :
                        ?>
                        <label style="display: block; margin: 5px 0;">
                            <input type="checkbox" 
                                   name="settings[categories][]" 
                                   value="<?php echo esc_attr($cat_id); ?>" 
                                   <?php checked(in_array($cat_id, $settings['categories'] ?? array())); ?> />
                            <?php echo esc_html($cat_name); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Features', 'spiralengine'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[favorites_enabled]" 
                               value="1" 
                               <?php checked($settings['favorites_enabled']); ?> />
                        <?php _e('Allow favoriting strategies', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[custom_strategies]" 
                               value="1" 
                               <?php checked($settings['custom_strategies']); ?> />
                        <?php _e('Allow custom strategies', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[effectiveness_tracking]" 
                               value="1" 
                               <?php checked($settings['effectiveness_tracking']); ?> />
                        <?php _e('Track strategy effectiveness', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render goal tracker settings
     *
     * @param array $settings
     */
    private function render_goal_tracker_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php _e('Goal Types', 'spiralengine'); ?></label>
                </th>
                <td>
                    <?php
                    $types = array(
                        'daily' => __('Daily Goals', 'spiralengine'),
                        'weekly' => __('Weekly Goals', 'spiralengine'),
                        'monthly' => __('Monthly Goals', 'spiralengine'),
                        'yearly' => __('Yearly Goals', 'spiralengine'),
                        'milestone' => __('Milestone Goals', 'spiralengine')
                    );
                    
                    foreach ($types as $type_id => $type_name) :
                        ?>
                        <label style="display: block; margin: 5px 0;">
                            <input type="checkbox" 
                                   name="settings[goal_types][]" 
                                   value="<?php echo esc_attr($type_id); ?>" 
                                   <?php checked(in_array($type_id, $settings['goal_types'] ?? array())); ?> />
                            <?php echo esc_html($type_name); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Features', 'spiralengine'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[progress_visualization]" 
                               value="1" 
                               <?php checked($settings['progress_visualization']); ?> />
                        <?php _e('Visual progress tracking', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[milestone_celebrations]" 
                               value="1" 
                               <?php checked($settings['milestone_celebrations']); ?> />
                        <?php _e('Celebrate milestones', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[accountability_partners]" 
                               value="1" 
                               <?php checked($settings['accountability_partners']); ?> />
                        <?php _e('Enable accountability partners', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render symptom tracker settings
     *
     * @param array $settings
     */
    private function render_symptom_tracker_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="severity_scale"><?php _e('Severity Scale', 'spiralengine'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="severity_scale" 
                           name="settings[severity_scale]" 
                           value="<?php echo esc_attr($settings['severity_scale']); ?>" 
                           min="5" 
                           max="10" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e('Symptom Categories', 'spiralengine'); ?></label>
                </th>
                <td>
                    <div id="symptom-categories">
                        <?php
                        $default_categories = $settings['symptom_categories'] ?? array();
                        if (empty($default_categories)) {
                            $default_categories = array(
                                __('Physical', 'spiralengine'),
                                __('Emotional', 'spiralengine'),
                                __('Cognitive', 'spiralengine'),
                                __('Behavioral', 'spiralengine')
                            );
                        }
                        
                        foreach ($default_categories as $index => $category) :
                            ?>
                            <div class="symptom-category-item" style="margin: 5px 0;">
                                <input type="text" 
                                       name="settings[symptom_categories][]" 
                                       value="<?php echo esc_attr($category); ?>" 
                                       class="regular-text" />
                                <button type="button" class="button remove-category">
                                    <?php _e('Remove', 'spiralengine'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="button add-category" style="margin-top: 10px;">
                        <?php _e('Add Category', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Features', 'spiralengine'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[medication_tracking]" 
                               value="1" 
                               <?php checked($settings['medication_tracking']); ?> />
                        <?php _e('Enable medication tracking', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[export_enabled]" 
                               value="1" 
                               <?php checked($settings['export_enabled']); ?> />
                        <?php _e('Allow data export for healthcare providers', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render AI chat settings
     *
     * @param array $settings
     */
    private function render_ai_chat_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ai_personality"><?php _e('AI Personality', 'spiralengine'); ?></label>
                </th>
                <td>
                    <select id="ai_personality" name="settings[personality]">
                        <option value="supportive" <?php selected($settings['personality'], 'supportive'); ?>>
                            <?php _e('Supportive & Encouraging', 'spiralengine'); ?>
                        </option>
                        <option value="clinical" <?php selected($settings['personality'], 'clinical'); ?>>
                            <?php _e('Clinical & Professional', 'spiralengine'); ?>
                        </option>
                        <option value="friendly" <?php selected($settings['personality'], 'friendly'); ?>>
                            <?php _e('Friendly & Casual', 'spiralengine'); ?>
                        </option>
                        <option value="coach" <?php selected($settings['personality'], 'coach'); ?>>
                            <?php _e('Motivational Coach', 'spiralengine'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="response_style"><?php _e('Response Style', 'spiralengine'); ?></label>
                </th>
                <td>
                    <select id="response_style" name="settings[response_style]">
                        <option value="empathetic" <?php selected($settings['response_style'], 'empathetic'); ?>>
                            <?php _e('Empathetic', 'spiralengine'); ?>
                        </option>
                        <option value="solution_focused" <?php selected($settings['response_style'], 'solution_focused'); ?>>
                            <?php _e('Solution-Focused', 'spiralengine'); ?>
                        </option>
                        <option value="reflective" <?php selected($settings['response_style'], 'reflective'); ?>>
                            <?php _e('Reflective Listening', 'spiralengine'); ?>
                        </option>
                        <option value="educational" <?php selected($settings['response_style'], 'educational'); ?>>
                            <?php _e('Educational', 'spiralengine'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Safety Features', 'spiralengine'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[session_memory]" 
                               value="1" 
                               <?php checked($settings['session_memory']); ?> />
                        <?php _e('Remember conversation context', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[crisis_detection]" 
                               value="1" 
                               <?php checked($settings['crisis_detection']); ?> />
                        <?php _e('Enable crisis detection', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e('Disclaimer Text', 'spiralengine'); ?></label>
                </th>
                <td>
                    <textarea name="settings[disclaimer]" 
                              rows="3" 
                              class="large-text"><?php 
                        echo esc_textarea($settings['disclaimer'] ?? __('This AI assistant provides general wellness support and is not a substitute for professional mental health care.', 'spiralengine')); 
                    ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render insights dashboard settings
     *
     * @param array $settings
     */
    private function render_insights_dashboard_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php _e('Time Ranges', 'spiralengine'); ?></label>
                </th>
                <td>
                    <?php
                    $ranges = array(
                        'day' => __('Daily', 'spiralengine'),
                        'week' => __('Weekly', 'spiralengine'),
                        'month' => __('Monthly', 'spiralengine'),
                        'quarter' => __('Quarterly', 'spiralengine'),
                        'year' => __('Yearly', 'spiralengine'),
                        'all_time' => __('All Time', 'spiralengine')
                    );
                    
                    foreach ($ranges as $range_id => $range_name) :
                        ?>
                        <label style="display: block; margin: 5px 0;">
                            <input type="checkbox" 
                                   name="settings[time_ranges][]" 
                                   value="<?php echo esc_attr($range_id); ?>" 
                                   <?php checked(in_array($range_id, $settings['time_ranges'] ?? array())); ?> />
                            <?php echo esc_html($range_name); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Analysis Features', 'spiralengine'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[correlation_analysis]" 
                               value="1" 
                               <?php checked($settings['correlation_analysis']); ?> />
                        <?php _e('Enable correlation analysis', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[predictive_insights]" 
                               value="1" 
                               <?php checked($settings['predictive_insights']); ?> />
                        <?php _e('Show predictive insights', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[export_reports]" 
                               value="1" 
                               <?php checked($settings['export_reports']); ?> />
                        <?php _e('Allow report exports', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e('Chart Types', 'spiralengine'); ?></label>
                </th>
                <td>
                    <?php
                    $charts = array(
                        'line' => __('Line Charts', 'spiralengine'),
                        'bar' => __('Bar Charts', 'spiralengine'),
                        'pie' => __('Pie Charts', 'spiralengine'),
                        'heatmap' => __('Heat Maps', 'spiralengine'),
                        'scatter' => __('Scatter Plots', 'spiralengine')
                    );
                    
                    foreach ($charts as $chart_id => $chart_name) :
                        ?>
                        <label style="display: inline-block; margin: 5px 10px 5px 0;">
                            <input type="checkbox" 
                                   name="settings[chart_types][]" 
                                   value="<?php echo esc_attr($chart_id); ?>" 
                                   <?php checked(in_array($chart_id, $settings['chart_types'] ?? array())); ?> />
                            <?php echo esc_html($chart_name); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render crisis toolkit settings
     *
     * @param array $settings
     */
    private function render_crisis_toolkit_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php _e('Emergency Contacts', 'spiralengine'); ?></label>
                </th>
                <td>
                    <div id="emergency-contacts">
                        <?php
                        $contacts = $settings['emergency_contacts'] ?? array();
                        if (empty($contacts)) {
                            $contacts = array(array('name' => '', 'phone' => '', 'relationship' => ''));
                        }
                        
                        foreach ($contacts as $index => $contact) :
                            ?>
                            <div class="emergency-contact-item" style="background: #f9f9f9; padding: 10px; margin: 10px 0;">
                                <input type="text" 
                                       name="settings[emergency_contacts][<?php echo $index; ?>][name]" 
                                       value="<?php echo esc_attr($contact['name']); ?>" 
                                       placeholder="<?php esc_attr_e('Contact Name', 'spiralengine'); ?>" />
                                
                                <input type="tel" 
                                       name="settings[emergency_contacts][<?php echo $index; ?>][phone]" 
                                       value="<?php echo esc_attr($contact['phone']); ?>" 
                                       placeholder="<?php esc_attr_e('Phone Number', 'spiralengine'); ?>" />
                                
                                <input type="text" 
                                       name="settings[emergency_contacts][<?php echo $index; ?>][relationship]" 
                                       value="<?php echo esc_attr($contact['relationship']); ?>" 
                                       placeholder="<?php esc_attr_e('Relationship', 'spiralengine'); ?>" />
                                
                                <button type="button" class="button remove-contact">
                                    <?php _e('Remove', 'spiralengine'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="button add-contact" style="margin-top: 10px;">
                        <?php _e('Add Contact', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Crisis Resources', 'spiralengine'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[safety_plan]" 
                               value="1" 
                               <?php checked($settings['safety_plan']); ?> />
                        <?php _e('Enable safety plan creation', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[grounding_exercises]" 
                               value="1" 
                               <?php checked($settings['grounding_exercises']); ?> />
                        <?php _e('Include grounding exercises', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" 
                               name="settings[hotline_numbers]" 
                               value="1" 
                               <?php checked($settings['hotline_numbers']); ?> />
                        <?php _e('Show crisis hotline numbers', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render generic widget settings
     *
     * @param string $widget_id
     * @param array $settings
     */
    private function render_generic_settings($widget_id, $settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php _e('Widget Settings', 'spiralengine'); ?></label>
                </th>
                <td>
                    <p><?php _e('Custom settings for this widget can be configured here.', 'spiralengine'); ?></p>
                    <?php
                    // Allow custom settings via filter
                    do_action('spiralengine_widget_settings_' . $widget_id, $settings);
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Check if user can access widget
     *
     * @param string $widget_id
     * @param string $user_tier
     * @return bool
     */
    private function user_can_access_widget($widget_id, $user_tier) {
        if (!isset($this->widgets[$widget_id])) {
            return false;
        }
        
        $required_tier = $this->widgets[$widget_id]['tier_requirement'];
        
        if ($required_tier === 'free') {
            return true;
        }
        
        $tier_hierarchy = array(
            'free' => 0,
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4
        );
        
        $user_level = $tier_hierarchy[$user_tier] ?? 0;
        $required_level = $tier_hierarchy[$required_tier] ?? 999;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Get widget usage statistics
     *
     * @param string $widget_id
     * @return array
     */
    private function get_widget_usage_stats($widget_id) {
        global $wpdb;
        
        $cache_key = 'spiralengine_widget_stats_' . $widget_id;
        $stats = get_transient($cache_key);
        
        if (false === $stats) {
            $stats = array(
                'total_users' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT user_id) 
                    FROM {$wpdb->prefix}spiralengine_episodes 
                    WHERE widget_id = %s",
                    $widget_id
                )),
                'total_episodes' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) 
                    FROM {$wpdb->prefix}spiralengine_episodes 
                    WHERE widget_id = %s",
                    $widget_id
                ))
            );
            
            set_transient($cache_key, $stats, HOUR_IN_SECONDS);
        }
        
        return $stats;
    }
    
    /**
     * AJAX: Save widget settings
     */
    public function ajax_save_widget() {
        check_ajax_referer('spiralengine_widget_management', 'nonce');
        
        if (!current_user_can('spiralengine_manage_widgets')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'spiralengine')));
        }
        
        $widget_id = sanitize_key($_POST['widget_id']);
        $settings = $_POST['settings'] ?? array();
        
        // Sanitize settings based on widget type
        $sanitized_settings = $this->sanitize_widget_settings($widget_id, $settings);
        
        // Save settings
        $all_settings = get_option('spiralengine_widget_settings', array());
        $all_settings[$widget_id] = $sanitized_settings;
        update_option('spiralengine_widget_settings', $all_settings);
        
        // Clear cache
        delete_transient('spiralengine_widget_settings_' . $widget_id);
        
        wp_send_json_success(array('message' => __('Widget settings saved successfully.', 'spiralengine')));
    }
    
    /**
     * AJAX: Toggle widget enabled state
     */
    public function ajax_toggle_widget() {
        check_ajax_referer('spiralengine_widget_management', 'nonce');
        
        if (!current_user_can('spiralengine_manage_widgets')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'spiralengine')));
        }
        
        $widget_id = sanitize_key($_POST['widget_id']);
        $enabled = !empty($_POST['enabled']);
        
        $enabled_widgets = get_option('spiralengine_enabled_widgets', array());
        
        if ($enabled) {
            if (!in_array($widget_id, $enabled_widgets)) {
                $enabled_widgets[] = $widget_id;
            }
        } else {
            $enabled_widgets = array_diff($enabled_widgets, array($widget_id));
        }
        
        update_option('spiralengine_enabled_widgets', array_values($enabled_widgets));
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Reset widget to default settings
     */
    public function ajax_reset_widget() {
        check_ajax_referer('spiralengine_widget_management', 'nonce');
        
        if (!current_user_can('spiralengine_manage_widgets')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'spiralengine')));
        }
        
        $widget_id = sanitize_key($_POST['widget_id']);
        
        $all_settings = get_option('spiralengine_widget_settings', array());
        unset($all_settings[$widget_id]);
        update_option('spiralengine_widget_settings', $all_settings);
        
        // Clear cache
        delete_transient('spiralengine_widget_settings_' . $widget_id);
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Preview widget
     */
    public function ajax_preview_widget() {
        check_ajax_referer('spiralengine_widget_management', 'nonce');
        
        if (!current_user_can('spiralengine_manage_widgets')) {
            wp_send_json_error();
        }
        
        $widget_id = sanitize_key($_POST['widget_id']);
        
        ob_start();
        ?>
        <div class="spiralengine-widget-preview">
            <div class="preview-header">
                <h3><?php echo esc_html($this->widgets[$widget_id]['name']); ?></h3>
                <p><?php echo esc_html($this->widgets[$widget_id]['description']); ?></p>
            </div>
            
            <div class="preview-content">
                <iframe src="<?php echo add_query_arg(array(
                    'spiralengine_widget_preview' => $widget_id,
                    'nonce' => wp_create_nonce('widget_preview_' . $widget_id)
                ), home_url('/')); ?>" width="100%" height="500" frameborder="0"></iframe>
            </div>
            
            <div class="preview-footer">
                <p><?php _e('This is a preview of how the widget will appear to users.', 'spiralengine'); ?></p>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Reorder widgets
     */
    public function ajax_reorder_widgets() {
        check_ajax_referer('spiralengine_widget_management', 'nonce');
        
        if (!current_user_can('spiralengine_manage_widgets')) {
            wp_send_json_error();
        }
        
        $order = $_POST['order'] ?? array();
        
        // Save widget order
        update_option('spiralengine_widget_order', $order);
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Duplicate widget
     */
    public function ajax_duplicate_widget() {
        check_ajax_referer('spiralengine_widget_management', 'nonce');
        
        if (!current_user_can('spiralengine_manage_widgets')) {
            wp_send_json_error();
        }
        
        $widget_id = sanitize_key($_POST['widget_id']);
        
        // This would create a custom widget instance
        // For now, we'll just return success
        
        wp_send_json_success();
    }
    
    /**
     * Sanitize widget settings
     *
     * @param string $widget_id
     * @param array $settings
     * @return array
     */
    private function sanitize_widget_settings($widget_id, $settings) {
        $sanitized = array();
        
        // Generic sanitization
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = array_map('sanitize_text_field', $value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = intval($value);
            } elseif ($key === 'color' || strpos($key, '_color') !== false) {
                $sanitized[$key] = sanitize_hex_color($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        // Widget-specific sanitization
        $sanitized = apply_filters('spiralengine_sanitize_widget_settings', $sanitized, $widget_id);
        
        return $sanitized;
    }
}


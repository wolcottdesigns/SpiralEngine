<?php
// includes/class-spiralengine-shortcodes.php

/**
 * SPIRAL Engine Shortcodes Handler
 *
 * Main shortcode handler for widget rendering with caching,
 * error handling, and admin preview support
 *
 * @package SPIRAL_Engine
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SpiralEngine_Shortcodes {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cache settings
     */
    private $cache_enabled = true;
    private $cache_duration = 3600; // 1 hour default
    
    /**
     * Rendered widgets tracking (prevent infinite loops)
     */
    private $rendered_widgets = array();
    
    /**
     * Widget registry instance
     */
    private $registry;
    
    /**
     * Restrictions handler
     */
    private $restrictions;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
        $this->register_shortcodes();
        $this->setup_hooks();
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize
     */
    private function init() {
        // Get instances
        $this->registry = SpiralEngine_Widget_Registry::get_instance();
        $this->restrictions = SpiralEngine_Widget_Restrictions::get_instance();
        
        // Load cache settings
        $this->cache_enabled = get_option('spiralengine_widget_cache_enabled', true);
        $this->cache_duration = get_option('spiralengine_widget_cache_duration', 3600);
    }
    
    /**
     * Register shortcodes
     */
    private function register_shortcodes() {
        // Main widget shortcode
        add_shortcode('spiralengine_widget', array($this, 'render_widget_shortcode'));
        
        // Alias shortcodes
        add_shortcode('spiral_widget', array($this, 'render_widget_shortcode'));
        add_shortcode('se_widget', array($this, 'render_widget_shortcode'));
        
        // Episode widget shortcode
        add_shortcode('spiralengine_episode', array($this, 'render_episode_shortcode'));
        
        // Widget list shortcode
        add_shortcode('spiralengine_widget_list', array($this, 'render_widget_list_shortcode'));
        
        // Widget grid shortcode
        add_shortcode('spiralengine_widget_grid', array($this, 'render_widget_grid_shortcode'));
    }
    
    /**
     * Setup hooks
     */
    private function setup_hooks() {
        // AJAX handlers for dynamic loading
        add_action('wp_ajax_spiralengine_load_widget', array($this, 'ajax_load_widget'));
        add_action('wp_ajax_nopriv_spiralengine_load_widget', array($this, 'ajax_load_widget'));
        
        // Clear cache hooks
        add_action('spiralengine_widget_updated', array($this, 'clear_widget_cache'));
        add_action('spiralengine_widget_settings_updated', array($this, 'clear_widget_cache'));
        
        // Admin preview
        add_filter('the_content', array($this, 'add_admin_preview_controls'), 999);
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Main widget shortcode handler
     * [spiralengine_widget id="widget_id" mode="preview|full" ...]
     */
    public function render_widget_shortcode($atts, $content = null, $tag = '') {
        // Parse attributes
        $atts = shortcode_atts(array(
            'id' => '',
            'uuid' => '',
            'mode' => 'preview',
            'cache' => null,
            'class' => '',
            'section' => '',
            'ajax' => 'false',
            'fallback' => '',
            'debug' => 'false'
        ), $atts, $tag);
        
        // Validate widget identifier
        if (empty($atts['id']) && empty($atts['uuid'])) {
            return $this->render_error('Widget ID or UUID required', $atts['debug'] === 'true');
        }
        
        // Get widget
        if (!empty($atts['uuid'])) {
            $widget = $this->registry->get_widget($atts['uuid']);
        } else {
            $widget = $this->registry->get_widget_by_id($atts['id']);
        }
        
        if (!$widget) {
            return $this->render_error('Widget not found: ' . ($atts['id'] ?: $atts['uuid']), $atts['debug'] === 'true');
        }
        
        // Check for infinite loops
        $widget_key = $widget['uuid'] . '_' . $atts['mode'];
        if (in_array($widget_key, $this->rendered_widgets)) {
            return $this->render_error('Widget loop detected', $atts['debug'] === 'true');
        }
        
        // Add to rendered list
        $this->rendered_widgets[] = $widget_key;
        
        try {
            // Check if widget is active
            if ($widget['status'] !== 'active') {
                return $this->render_inactive_widget($widget, $atts);
            }
            
            // Check access permissions
            $access = $this->restrictions->check_widget_access($widget['config']);
            if (!$access['access'] && $atts['ajax'] !== 'true') {
                return $this->render_restricted_widget($widget, $access, $atts);
            }
            
            // Get cache if enabled
            if ($this->should_use_cache($atts)) {
                $cached_content = $this->get_cached_widget($widget['uuid'], $atts);
                if ($cached_content !== false) {
                    // Remove from rendered list
                    array_pop($this->rendered_widgets);
                    return $cached_content;
                }
            }
            
            // Render widget
            if ($atts['ajax'] === 'true') {
                $output = $this->render_ajax_widget($widget, $atts);
            } else {
                $output = $this->render_widget($widget, $atts);
            }
            
            // Cache if enabled
            if ($this->should_use_cache($atts) && !empty($output)) {
                $this->cache_widget($widget['uuid'], $atts, $output);
            }
            
            // Remove from rendered list
            array_pop($this->rendered_widgets);
            
            return $output;
            
        } catch (Exception $e) {
            // Remove from rendered list
            array_pop($this->rendered_widgets);
            
            // Log error
            error_log('SPIRAL Engine Widget Error: ' . $e->getMessage());
            
            return $this->render_error('Widget error: ' . $e->getMessage(), $atts['debug'] === 'true');
        }
    }
    
    /**
     * Episode widget shortcode
     * [spiralengine_episode type="depression" feature="logger|patterns|forecast"]
     */
    public function render_episode_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => '',
            'feature' => 'logger',
            'mode' => 'full',
            'class' => ''
        ), $atts);
        
        if (empty($atts['type'])) {
            return $this->render_error('Episode type required');
        }
        
        // Map episode type to widget ID
        $widget_id = 'episode_' . $atts['type'] . '_' . $atts['feature'];
        
        // Render using main widget shortcode
        return $this->render_widget_shortcode(array(
            'id' => $widget_id,
            'mode' => $atts['mode'],
            'class' => 'sp-episode-widget ' . $atts['class']
        ));
    }
    
    /**
     * Widget list shortcode
     * [spiralengine_widget_list category="episode_loggers" columns="3"]
     */
    public function render_widget_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'type' => '',
            'limit' => 10,
            'orderby' => 'name',
            'order' => 'ASC',
            'columns' => 1,
            'show_description' => 'true',
            'link_to_full' => 'true',
            'class' => ''
        ), $atts);
        
        // Get widgets
        if (!empty($atts['category'])) {
            $widgets = $this->registry->get_widgets_by_category($atts['category']);
        } elseif (!empty($atts['type'])) {
            $widgets = $this->registry->get_widgets_by_type($atts['type']);
        } else {
            $widgets = $this->registry->get_all_widgets();
        }
        
        // Filter active widgets only
        $widgets = array_filter($widgets, function($widget) {
            return $widget['status'] === 'active';
        });
        
        // Sort widgets
        $this->sort_widgets($widgets, $atts['orderby'], $atts['order']);
        
        // Limit
        if ($atts['limit'] > 0) {
            $widgets = array_slice($widgets, 0, $atts['limit']);
        }
        
        // Render list
        ob_start();
        ?>
        <div class="sp-widget-list sp-columns-<?php echo esc_attr($atts['columns']); ?> <?php echo esc_attr($atts['class']); ?>">
            <?php foreach ($widgets as $widget): ?>
                <?php $this->render_widget_list_item($widget, $atts); ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Widget grid shortcode
     * [spiralengine_widget_grid ids="widget1,widget2,widget3" columns="3"]
     */
    public function render_widget_grid_shortcode($atts) {
        $atts = shortcode_atts(array(
            'ids' => '',
            'uuids' => '',
            'columns' => 3,
            'gap' => '20',
            'mode' => 'preview',
            'class' => ''
        ), $atts);
        
        // Get widget IDs
        $widget_ids = array();
        if (!empty($atts['ids'])) {
            $widget_ids = array_map('trim', explode(',', $atts['ids']));
        } elseif (!empty($atts['uuids'])) {
            $widget_uuids = array_map('trim', explode(',', $atts['uuids']));
            // Convert UUIDs to IDs
            foreach ($widget_uuids as $uuid) {
                $widget = $this->registry->get_widget($uuid);
                if ($widget) {
                    $widget_ids[] = $widget['config']['widget_id'];
                }
            }
        }
        
        if (empty($widget_ids)) {
            return $this->render_error('No widgets specified');
        }
        
        // Render grid
        ob_start();
        ?>
        <div class="sp-widget-grid sp-grid-<?php echo esc_attr($atts['columns']); ?> <?php echo esc_attr($atts['class']); ?>" 
             style="gap: <?php echo esc_attr($atts['gap']); ?>px;">
            <?php foreach ($widget_ids as $widget_id): ?>
                <div class="sp-grid-item">
                    <?php echo do_shortcode(sprintf(
                        '[spiralengine_widget id="%s" mode="%s"]',
                        esc_attr($widget_id),
                        esc_attr($atts['mode'])
                    )); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render widget
     */
    private function render_widget($widget, $atts) {
        // Get widget instance
        $instance = $this->registry->get_widget_instance($widget['uuid']);
        
        if (!$instance) {
            return $this->render_error('Widget instance not available');
        }
        
        // Start output buffering
        ob_start();
        
        // Add wrapper
        $wrapper_classes = array(
            'sp-widget-wrapper',
            'sp-widget-' . $widget['config']['widget_id'],
            $atts['class']
        );
        
        if ($this->is_admin_preview()) {
            $wrapper_classes[] = 'sp-admin-preview';
        }
        
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" 
             data-widget-uuid="<?php echo esc_attr($widget['uuid']); ?>"
             data-widget-id="<?php echo esc_attr($widget['config']['widget_id']); ?>">
            <?php
            // Track render time
            $start_time = microtime(true);
            
            // Render widget
            $instance->render_widget(array(
                'section' => $atts['section']
            ), $atts['mode']);
            
            // Track performance
            $render_time = (microtime(true) - $start_time) * 1000; // Convert to ms
            do_action('spiralengine_widget_rendered', $widget['config']['widget_id'], $atts['mode'], $render_time);
            ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render AJAX widget placeholder
     */
    private function render_ajax_widget($widget, $atts) {
        $widget_id = 'sp-ajax-widget-' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($widget_id); ?>" 
             class="sp-widget-ajax-container <?php echo esc_attr($atts['class']); ?>"
             data-widget-uuid="<?php echo esc_attr($widget['uuid']); ?>"
             data-widget-id="<?php echo esc_attr($widget['config']['widget_id']); ?>"
             data-widget-mode="<?php echo esc_attr($atts['mode']); ?>"
             data-widget-section="<?php echo esc_attr($atts['section']); ?>">
            <div class="sp-widget-loading">
                <span class="sp-spinner"></span>
                <span class="sp-loading-text"><?php _e('Loading widget...', 'spiralengine'); ?></span>
            </div>
        </div>
        <?php if (!empty($atts['fallback'])): ?>
            <noscript>
                <?php echo wp_kses_post($atts['fallback']); ?>
            </noscript>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render restricted widget
     */
    private function render_restricted_widget($widget, $access_info, $atts) {
        ob_start();
        ?>
        <div class="sp-widget-wrapper sp-widget-restricted <?php echo esc_attr($atts['class']); ?>" 
             data-widget-id="<?php echo esc_attr($widget['config']['widget_id']); ?>">
            <?php $this->restrictions->render_restriction($access_info, $widget['config']['widget_id']); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render inactive widget
     */
    private function render_inactive_widget($widget, $atts) {
        if (!current_user_can('manage_options')) {
            return ''; // Don't show inactive widgets to regular users
        }
        
        ob_start();
        ?>
        <div class="sp-widget-wrapper sp-widget-inactive <?php echo esc_attr($atts['class']); ?>">
            <div class="sp-inactive-notice">
                <span class="dashicons dashicons-warning"></span>
                <p><?php printf(__('Widget "%s" is inactive', 'spiralengine'), esc_html($widget['config']['widget_name'])); ?></p>
                <?php if (current_user_can('manage_options')): ?>
                    <a href="<?php echo admin_url('admin.php?page=spiralengine-widget-studio'); ?>" class="button button-small">
                        <?php _e('Manage Widgets', 'spiralengine'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render widget list item
     */
    private function render_widget_list_item($widget, $atts) {
        $widget_url = home_url('/widgets/' . $widget['config']['widget_id']);
        ?>
        <div class="sp-widget-list-item">
            <div class="sp-widget-icon">
                <span class="<?php echo esc_attr($widget['config']['widget_icon']); ?>"></span>
            </div>
            <div class="sp-widget-info">
                <h3 class="sp-widget-title">
                    <?php if ($atts['link_to_full'] === 'true'): ?>
                        <a href="<?php echo esc_url($widget_url); ?>">
                            <?php echo esc_html($widget['config']['widget_name']); ?>
                        </a>
                    <?php else: ?>
                        <?php echo esc_html($widget['config']['widget_name']); ?>
                    <?php endif; ?>
                </h3>
                <?php if ($atts['show_description'] === 'true' && !empty($widget['config']['widget_description'])): ?>
                    <p class="sp-widget-description">
                        <?php echo esc_html($widget['config']['widget_description']); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render error message
     */
    private function render_error($message, $show_debug = false) {
        if (!$show_debug && !current_user_can('manage_options')) {
            return ''; // Don't show errors to regular users unless debug is on
        }
        
        return sprintf(
            '<div class="sp-widget-error"><p>%s</p></div>',
            esc_html($message)
        );
    }
    
    /**
     * Sort widgets array
     */
    private function sort_widgets(&$widgets, $orderby, $order) {
        usort($widgets, function($a, $b) use ($orderby, $order) {
            $field_a = $a['config']['widget_' . $orderby] ?? '';
            $field_b = $b['config']['widget_' . $orderby] ?? '';
            
            if ($order === 'ASC') {
                return strcasecmp($field_a, $field_b);
            } else {
                return strcasecmp($field_b, $field_a);
            }
        });
    }
    
    /**
     * Check if should use cache
     */
    private function should_use_cache($atts) {
        // Check global setting
        if (!$this->cache_enabled) {
            return false;
        }
        
        // Check shortcode attribute
        if ($atts['cache'] === 'false') {
            return false;
        }
        
        // Don't cache in admin preview
        if ($this->is_admin_preview()) {
            return false;
        }
        
        // Don't cache for logged in users with manage_options
        if (current_user_can('manage_options') && $atts['cache'] !== 'true') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get cached widget content
     */
    private function get_cached_widget($widget_uuid, $atts) {
        $cache_key = $this->get_cache_key($widget_uuid, $atts);
        return get_transient($cache_key);
    }
    
    /**
     * Cache widget content
     */
    private function cache_widget($widget_uuid, $atts, $content) {
        $cache_key = $this->get_cache_key($widget_uuid, $atts);
        $duration = $atts['cache'] ?? $this->cache_duration;
        
        if (is_numeric($duration)) {
            set_transient($cache_key, $content, intval($duration));
        } else {
            set_transient($cache_key, $content, $this->cache_duration);
        }
    }
    
    /**
     * Get cache key
     */
    private function get_cache_key($widget_uuid, $atts) {
        $user_id = get_current_user_id();
        $key_parts = array(
            'spiralengine_widget',
            $widget_uuid,
            $atts['mode'],
            $atts['section'],
            $user_id
        );
        
        return implode('_', array_filter($key_parts));
    }
    
    /**
     * Clear widget cache
     */
    public function clear_widget_cache($widget_uuid) {
        global $wpdb;
        
        // Clear all transients for this widget
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '%spiralengine_widget_' . $widget_uuid . '%'
        ));
    }
    
    /**
     * AJAX load widget
     */
    public function ajax_load_widget() {
        // Verify nonce
        check_ajax_referer('spiralengine_ajax', 'nonce');
        
        $widget_uuid = sanitize_text_field($_POST['widget_uuid'] ?? '');
        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');
        $mode = sanitize_text_field($_POST['mode'] ?? 'preview');
        $section = sanitize_text_field($_POST['section'] ?? '');
        
        if (empty($widget_uuid) && empty($widget_id)) {
            wp_send_json_error('Widget identifier required');
        }
        
        // Get widget
        if (!empty($widget_uuid)) {
            $widget = $this->registry->get_widget($widget_uuid);
        } else {
            $widget = $this->registry->get_widget_by_id($widget_id);
        }
        
        if (!$widget) {
            wp_send_json_error('Widget not found');
        }
        
        // Check access
        $access = $this->restrictions->check_widget_access($widget['config']);
        if (!$access['access']) {
            ob_start();
            $this->restrictions->render_restriction($access, $widget['config']['widget_id']);
            $html = ob_get_clean();
            
            wp_send_json_success(array(
                'html' => $html,
                'restricted' => true
            ));
        }
        
        // Render widget
        $html = $this->render_widget($widget, array(
            'mode' => $mode,
            'section' => $section,
            'ajax' => 'false'
        ));
        
        wp_send_json_success(array(
            'html' => $html,
            'restricted' => false
        ));
    }
    
    /**
     * Check if in admin preview mode
     */
    private function is_admin_preview() {
        return is_preview() || (isset($_GET['preview']) && current_user_can('edit_posts'));
    }
    
    /**
     * Add admin preview controls
     */
    public function add_admin_preview_controls($content) {
        if (!$this->is_admin_preview() || !current_user_can('manage_options')) {
            return $content;
        }
        
        // Add preview toolbar
        $toolbar = '<div class="sp-preview-toolbar">';
        $toolbar .= '<span class="sp-preview-label">' . __('Widget Preview Mode', 'spiralengine') . '</span>';
        $toolbar .= '<button class="sp-preview-toggle" data-mode="preview">' . __('Preview', 'spiralengine') . '</button>';
        $toolbar .= '<button class="sp-preview-toggle" data-mode="full">' . __('Full', 'spiralengine') . '</button>';
        $toolbar .= '<button class="sp-preview-refresh">' . __('Refresh', 'spiralengine') . '</button>';
        $toolbar .= '</div>';
        
        return $toolbar . $content;
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        // Only enqueue if widgets are present
        if (!$this->has_widgets_on_page()) {
            return;
        }
        
        wp_enqueue_script(
            'spiralengine-widgets',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/spiralengine-widgets.js',
            array('jquery'),
            SPIRALENGINE_VERSION,
            true
        );
        
        wp_localize_script('spiralengine-widgets', 'spiralengineWidgets', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_ajax'),
            'strings' => array(
                'loading' => __('Loading...', 'spiralengine'),
                'error' => __('Error loading widget', 'spiralengine'),
                'retry' => __('Retry', 'spiralengine')
            )
        ));
        
        // Enqueue styles
        wp_enqueue_style(
            'spiralengine-widgets',
            SPIRALENGINE_PLUGIN_URL . 'assets/css/spiralengine-widgets.css',
            array(),
            SPIRALENGINE_VERSION
        );
    }
    
    /**
     * Check if page has widgets
     */
    private function has_widgets_on_page() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check for shortcodes
        $shortcodes = array(
            'spiralengine_widget',
            'spiral_widget',
            'se_widget',
            'spiralengine_episode',
            'spiralengine_widget_list',
            'spiralengine_widget_grid'
        );
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        // Check for widgets in widget areas
        if (is_active_widget(false, false, 'spiralengine_widget')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get available widget attributes for documentation
     */
    public static function get_shortcode_attributes() {
        return array(
            'spiralengine_widget' => array(
                'id' => 'Widget ID (required if uuid not provided)',
                'uuid' => 'Widget UUID (required if id not provided)',
                'mode' => 'Display mode: preview or full (default: preview)',
                'cache' => 'Enable caching: true/false or cache duration in seconds',
                'class' => 'Additional CSS classes',
                'section' => 'Specific section to display',
                'ajax' => 'Load via AJAX: true/false (default: false)',
                'fallback' => 'Fallback content for no JavaScript',
                'debug' => 'Show debug information: true/false (default: false)'
            ),
            'spiralengine_episode' => array(
                'type' => 'Episode type (required)',
                'feature' => 'Feature to display: logger, patterns, forecast (default: logger)',
                'mode' => 'Display mode: preview or full (default: full)',
                'class' => 'Additional CSS classes'
            ),
            'spiralengine_widget_list' => array(
                'category' => 'Filter by category',
                'type' => 'Filter by type',
                'limit' => 'Maximum widgets to show (default: 10)',
                'orderby' => 'Order by: name, type, category (default: name)',
                'order' => 'Sort order: ASC or DESC (default: ASC)',
                'columns' => 'Number of columns: 1-4 (default: 1)',
                'show_description' => 'Show descriptions: true/false (default: true)',
                'link_to_full' => 'Link to full widget: true/false (default: true)',
                'class' => 'Additional CSS classes'
            ),
            'spiralengine_widget_grid' => array(
                'ids' => 'Comma-separated widget IDs',
                'uuids' => 'Comma-separated widget UUIDs',
                'columns' => 'Grid columns: 1-6 (default: 3)',
                'gap' => 'Gap between widgets in pixels (default: 20)',
                'mode' => 'Display mode: preview or full (default: preview)',
                'class' => 'Additional CSS classes'
            )
        );
    }
}

// Initialize shortcodes
add_action('init', function() {
    SpiralEngine_Shortcodes::get_instance();
});

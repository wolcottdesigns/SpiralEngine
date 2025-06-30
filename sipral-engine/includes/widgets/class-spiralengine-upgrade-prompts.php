<?php
/**
 * SPIRAL Engine Upgrade Prompts Widget
 * 
 * @package    SPIRAL_Engine
 * @subpackage Widgets
 * @file       includes/widgets/class-spiralengine-upgrade-prompts.php
 * @since      1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Upgrade Prompts Widget Class
 * 
 * Implements contextual upgrade prompts with A/B testing and conversion tracking
 */
class SpiralEngine_Upgrade_Prompts {
    
    /**
     * Instance of this class
     * 
     * @var SpiralEngine_Upgrade_Prompts
     */
    private static $instance = null;
    
    /**
     * Access control instance
     * 
     * @var SpiralEngine_Access_Control
     */
    private $access_control;
    
    /**
     * MemberPress instance
     * 
     * @var SpiralEngine_MemberPress
     */
    private $memberpress;
    
    /**
     * Current A/B test variants
     * 
     * @var array
     */
    private $ab_variants = array();
    
    /**
     * Upgrade prompt templates
     * 
     * @var array
     */
    private $prompt_templates = array(
        'pattern_detection' => array(
            'variants' => array(
                'a' => array(
                    'title' => 'Unlock Your Patterns',
                    'subtitle' => 'Discover when and why your episodes occur',
                    'benefits' => array(
                        'Identify time-based patterns',
                        'Track episode triggers',
                        'See severity trends',
                        'Get predictive insights'
                    ),
                    'cta' => 'Upgrade to Explorer',
                    'color_scheme' => 'gradient-blue'
                ),
                'b' => array(
                    'title' => 'Your Data Has Stories to Tell',
                    'subtitle' => 'Explorer membership reveals hidden patterns',
                    'benefits' => array(
                        'Know your high-risk times',
                        'Understand your triggers',
                        'Track improvement over time',
                        'Prevent future episodes'
                    ),
                    'cta' => 'Start Exploring',
                    'color_scheme' => 'gradient-purple'
                )
            )
        ),
        'correlation_insights' => array(
            'variants' => array(
                'a' => array(
                    'title' => 'See How Episodes Connect',
                    'subtitle' => 'Understand the relationships between different episode types',
                    'benefits' => array(
                        'Cross-episode correlations',
                        'Cascade effect detection',
                        'Combined trigger analysis',
                        'Holistic mental health view'
                    ),
                    'cta' => 'Unlock Correlations',
                    'color_scheme' => 'gradient-teal'
                ),
                'b' => array(
                    'title' => 'Everything is Connected',
                    'subtitle' => 'Discover how your episodes influence each other',
                    'benefits' => array(
                        'See episode relationships',
                        'Identify domino effects',
                        'Understand compound triggers',
                        'Get the complete picture'
                    ),
                    'cta' => 'Reveal Connections',
                    'color_scheme' => 'gradient-indigo'
                )
            )
        ),
        'ai_forecast' => array(
            'variants' => array(
                'a' => array(
                    'title' => 'AI-Powered Predictions',
                    'subtitle' => 'Know your risk before episodes strike',
                    'benefits' => array(
                        '7-day risk assessment',
                        'Personalized forecasts',
                        'Early warning alerts',
                        'Preventive recommendations'
                    ),
                    'cta' => 'Activate AI Forecasting',
                    'color_scheme' => 'gradient-orange'
                ),
                'b' => array(
                    'title' => 'Stay One Step Ahead',
                    'subtitle' => 'Let AI predict and prevent your episodes',
                    'benefits' => array(
                        'Daily risk predictions',
                        'Custom prevention tips',
                        'Smart notifications',
                        'Proactive mental health'
                    ),
                    'cta' => 'Enable Predictions',
                    'color_scheme' => 'gradient-pink'
                )
            )
        ),
        'api_access' => array(
            'variants' => array(
                'a' => array(
                    'title' => 'Ultimate Control',
                    'subtitle' => 'Integrate your data with any platform',
                    'benefits' => array(
                        'Full API access',
                        'Custom integrations',
                        'Automated workflows',
                        'Professional tools'
                    ),
                    'cta' => 'Go Voyager',
                    'color_scheme' => 'gradient-red'
                )
            )
        )
    );
    
    /**
     * Get instance
     * 
     * @return SpiralEngine_Upgrade_Prompts
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
        $this->access_control = SpiralEngine_Access_Control::get_instance();
        $this->memberpress = SpiralEngine_MemberPress::get_instance();
        
        $this->init_hooks();
        $this->load_ab_tests();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Widget rendering
        add_action('spiralengine_render_upgrade_prompt', array($this, 'render_upgrade_prompt'), 10, 2);
        add_filter('spiralengine_widget_restriction_content', array($this, 'get_restriction_content'), 10, 3);
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_track_upgrade_click', array($this, 'ajax_track_click'));
        add_action('wp_ajax_nopriv_spiralengine_track_upgrade_click', array($this, 'ajax_track_click'));
        
        add_action('wp_ajax_spiralengine_dismiss_upgrade_prompt', array($this, 'ajax_dismiss_prompt'));
        add_action('wp_ajax_nopriv_spiralengine_dismiss_upgrade_prompt', array($this, 'ajax_dismiss_prompt'));
        
        // Conversion tracking
        add_action('spiralengine_membership_updated', array($this, 'track_conversion'), 10, 3);
        
        // Scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Load A/B test configurations
     */
    private function load_ab_tests() {
        $this->ab_variants = get_option('spiralengine_ab_variants', array());
        
        // Initialize user variants if needed
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_variants = get_user_meta($user_id, 'spiralengine_ab_variants', true);
            
            if (!is_array($user_variants)) {
                $user_variants = array();
                
                // Assign variants for each prompt type
                foreach ($this->prompt_templates as $feature => $config) {
                    $variants = array_keys($config['variants']);
                    $user_variants[$feature] = $variants[array_rand($variants)];
                }
                
                update_user_meta($user_id, 'spiralengine_ab_variants', $user_variants);
            }
        }
    }
    
    /**
     * Render upgrade prompt
     * 
     * @param string $feature Feature key
     * @param array  $context Additional context
     */
    public function render_upgrade_prompt($feature, $context = array()) {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Check if user already has access
        $access = $this->access_control->check_feature_access($feature, $user_id);
        
        if ($access['has_access']) {
            return;
        }
        
        // Check if prompt was recently dismissed
        if ($this->is_prompt_dismissed($user_id, $feature)) {
            return;
        }
        
        // Get user's variant
        $variant = $this->get_user_variant($user_id, $feature);
        
        // Get prompt configuration
        $prompt = $this->get_prompt_config($feature, $variant);
        
        if (!$prompt) {
            return;
        }
        
        // Track impression
        $this->track_impression($user_id, $feature, $variant);
        
        // Render the prompt
        $this->output_prompt_html($prompt, $feature, $access, $context);
    }
    
    /**
     * Get restriction content for widgets
     * 
     * @param string $content Default content
     * @param string $section Section key
     * @param array  $access Access information
     * @return string
     */
    public function get_restriction_content($content, $section, $access) {
        if ($access['has_access']) {
            return $content;
        }
        
        // Map section to feature
        $feature_map = array(
            'pattern_analysis' => 'pattern_detection',
            'correlation_insights' => 'basic_correlations',
            'ai_insights' => 'ai_forecast',
            'forecast_section' => 'unified_forecast'
        );
        
        $feature = isset($feature_map[$section]) ? $feature_map[$section] : $section;
        
        // Get user's variant
        $user_id = get_current_user_id();
        $variant = $this->get_user_variant($user_id, $feature);
        
        // Get prompt configuration
        $prompt = $this->get_prompt_config($feature, $variant);
        
        if (!$prompt) {
            return $content;
        }
        
        // Generate inline prompt
        ob_start();
        $this->output_inline_prompt($prompt, $feature, $access);
        return ob_get_clean();
    }
    
    /**
     * Get user's A/B test variant
     * 
     * @param int    $user_id
     * @param string $feature
     * @return string
     */
    private function get_user_variant($user_id, $feature) {
        $user_variants = get_user_meta($user_id, 'spiralengine_ab_variants', true);
        
        if (!is_array($user_variants) || !isset($user_variants[$feature])) {
            return 'a'; // Default variant
        }
        
        return $user_variants[$feature];
    }
    
    /**
     * Get prompt configuration
     * 
     * @param string $feature
     * @param string $variant
     * @return array|null
     */
    private function get_prompt_config($feature, $variant) {
        if (!isset($this->prompt_templates[$feature])) {
            return null;
        }
        
        if (!isset($this->prompt_templates[$feature]['variants'][$variant])) {
            $variant = 'a'; // Fallback to default
        }
        
        return $this->prompt_templates[$feature]['variants'][$variant];
    }
    
    /**
     * Output prompt HTML
     * 
     * @param array  $prompt Prompt configuration
     * @param string $feature Feature key
     * @param array  $access Access information
     * @param array  $context Additional context
     */
    private function output_prompt_html($prompt, $feature, $access, $context = array()) {
        $prompt_id = 'spiralengine-upgrade-' . $feature;
        $tier_info = $this->memberpress->get_tier_info($access['required_tier']);
        ?>
        <div id="<?php echo esc_attr($prompt_id); ?>" 
             class="spiralengine-upgrade-prompt <?php echo esc_attr($prompt['color_scheme']); ?>"
             data-feature="<?php echo esc_attr($feature); ?>"
             data-required-tier="<?php echo esc_attr($access['required_tier']); ?>">
            
            <button class="sp-prompt-dismiss" aria-label="<?php esc_attr_e('Dismiss', 'spiralengine'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
            
            <div class="sp-prompt-content">
                <div class="sp-prompt-icon">
                    <span class="dashicons <?php echo esc_attr($tier_info['icon']); ?>"></span>
                </div>
                
                <h3 class="sp-prompt-title"><?php echo esc_html($prompt['title']); ?></h3>
                <p class="sp-prompt-subtitle"><?php echo esc_html($prompt['subtitle']); ?></p>
                
                <div class="sp-prompt-benefits">
                    <ul>
                        <?php foreach ($prompt['benefits'] as $benefit): ?>
                            <li>
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php echo esc_html($benefit); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="sp-prompt-actions">
                    <a href="<?php echo esc_url($access['upgrade_url']); ?>" 
                       class="sp-upgrade-button sp-track-click"
                       data-feature="<?php echo esc_attr($feature); ?>">
                        <?php echo esc_html($prompt['cta']); ?>
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </a>
                    
                    <p class="sp-prompt-tier-info">
                        <?php printf(
                            esc_html__('Requires %s membership', 'spiralengine'),
                            '<strong>' . esc_html($tier_info['name']) . '</strong>'
                        ); ?>
                    </p>
                </div>
            </div>
            
            <?php if (isset($context['show_preview']) && $context['show_preview']): ?>
                <div class="sp-prompt-preview">
                    <div class="sp-preview-blur">
                        <img src="<?php echo esc_url(SPIRALENGINE_URL . 'assets/images/feature-previews/' . $feature . '.png'); ?>" 
                             alt="<?php echo esc_attr($prompt['title']); ?>">
                    </div>
                    <p class="sp-preview-caption"><?php esc_html_e('Feature Preview', 'spiralengine'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Output inline prompt for widget sections
     * 
     * @param array  $prompt
     * @param string $feature
     * @param array  $access
     */
    private function output_inline_prompt($prompt, $feature, $access) {
        $tier_info = $this->memberpress->get_tier_info($access['required_tier']);
        ?>
        <div class="spiralengine-inline-upgrade <?php echo esc_attr($prompt['color_scheme']); ?>"
             data-feature="<?php echo esc_attr($feature); ?>">
            
            <div class="sp-inline-content">
                <h4 class="sp-inline-title">
                    <span class="dashicons <?php echo esc_attr($tier_info['icon']); ?>"></span>
                    <?php echo esc_html($prompt['title']); ?>
                </h4>
                
                <p class="sp-inline-description"><?php echo esc_html($prompt['subtitle']); ?></p>
                
                <div class="sp-inline-benefits">
                    <?php 
                    $benefits_preview = array_slice($prompt['benefits'], 0, 2);
                    foreach ($benefits_preview as $benefit): 
                    ?>
                        <span class="sp-benefit-tag">
                            <span class="dashicons dashicons-yes"></span>
                            <?php echo esc_html($benefit); ?>
                        </span>
                    <?php endforeach; ?>
                    
                    <?php if (count($prompt['benefits']) > 2): ?>
                        <span class="sp-more-benefits">
                            +<?php echo count($prompt['benefits']) - 2; ?> more
                        </span>
                    <?php endif; ?>
                </div>
                
                <a href="<?php echo esc_url($access['upgrade_url']); ?>" 
                   class="sp-inline-cta sp-track-click"
                   data-feature="<?php echo esc_attr($feature); ?>">
                    <?php echo esc_html($prompt['cta']); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Check if prompt is dismissed
     * 
     * @param int    $user_id
     * @param string $feature
     * @return bool
     */
    private function is_prompt_dismissed($user_id, $feature) {
        $dismissed = get_user_meta($user_id, 'spiralengine_dismissed_prompts', true);
        
        if (!is_array($dismissed)) {
            return false;
        }
        
        if (!isset($dismissed[$feature])) {
            return false;
        }
        
        // Check if dismissal has expired (7 days)
        $dismissed_time = $dismissed[$feature];
        $expiry_time = $dismissed_time + (7 * DAY_IN_SECONDS);
        
        return time() < $expiry_time;
    }
    
    /**
     * Track impression
     * 
     * @param int    $user_id
     * @param string $feature
     * @param string $variant
     */
    private function track_impression($user_id, $feature, $variant) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_upgrade_analytics';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'feature' => $feature,
                'variant' => $variant,
                'event_type' => 'impression',
                'timestamp' => current_time('mysql'),
                'user_tier' => $this->memberpress->get_user_tier($user_id)
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Ajax track click
     */
    public function ajax_track_click() {
        // Check nonce
        if (!check_ajax_referer('spiralengine_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $feature = isset($_POST['feature']) ? sanitize_text_field($_POST['feature']) : '';
        $user_id = get_current_user_id();
        
        if (empty($feature) || !$user_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        $variant = $this->get_user_variant($user_id, $feature);
        
        // Track click
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_upgrade_analytics';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'feature' => $feature,
                'variant' => $variant,
                'event_type' => 'click',
                'timestamp' => current_time('mysql'),
                'user_tier' => $this->memberpress->get_user_tier($user_id)
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        wp_send_json_success();
    }
    
    /**
     * Ajax dismiss prompt
     */
    public function ajax_dismiss_prompt() {
        // Check nonce
        if (!check_ajax_referer('spiralengine_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $feature = isset($_POST['feature']) ? sanitize_text_field($_POST['feature']) : '';
        $user_id = get_current_user_id();
        
        if (empty($feature) || !$user_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Get current dismissed prompts
        $dismissed = get_user_meta($user_id, 'spiralengine_dismissed_prompts', true);
        
        if (!is_array($dismissed)) {
            $dismissed = array();
        }
        
        // Add this dismissal
        $dismissed[$feature] = time();
        
        // Update user meta
        update_user_meta($user_id, 'spiralengine_dismissed_prompts', $dismissed);
        
        // Track dismissal
        $variant = $this->get_user_variant($user_id, $feature);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_upgrade_analytics';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'feature' => $feature,
                'variant' => $variant,
                'event_type' => 'dismiss',
                'timestamp' => current_time('mysql'),
                'user_tier' => $this->memberpress->get_user_tier($user_id)
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        wp_send_json_success();
    }
    
    /**
     * Track conversion
     * 
     * @param int    $user_id
     * @param string $new_tier
     * @param string $action
     */
    public function track_conversion($user_id, $new_tier, $action) {
        if ($action !== 'upgraded') {
            return;
        }
        
        // Check recent clicks
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_upgrade_analytics';
        
        // Find clicks in last 7 days
        $recent_clicks = $wpdb->get_results($wpdb->prepare(
            "SELECT feature, variant FROM $table_name 
             WHERE user_id = %d 
             AND event_type = 'click' 
             AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY timestamp DESC",
            $user_id
        ));
        
        foreach ($recent_clicks as $click) {
            // Track conversion
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'feature' => $click->feature,
                    'variant' => $click->variant,
                    'event_type' => 'conversion',
                    'timestamp' => current_time('mysql'),
                    'user_tier' => $new_tier
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!is_user_logged_in()) {
            return;
        }
        
        wp_enqueue_script(
            'spiralengine-upgrade-prompts',
            SPIRALENGINE_URL . 'assets/js/upgrade-prompts.js',
            array('jquery'),
            SPIRALENGINE_VERSION,
            true
        );
        
        wp_localize_script('spiralengine-upgrade-prompts', 'spiralengineUpgrade', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_ajax_nonce')
        ));
    }
    
    /**
     * Get conversion stats
     * 
     * @return array
     */
    public function get_conversion_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_upgrade_analytics';
        
        $stats = array();
        
        // Get stats for each feature and variant
        foreach ($this->prompt_templates as $feature => $config) {
            $stats[$feature] = array();
            
            foreach ($config['variants'] as $variant => $prompt) {
                // Get impressions
                $impressions = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name 
                     WHERE feature = %s AND variant = %s AND event_type = 'impression'",
                    $feature, $variant
                ));
                
                // Get clicks
                $clicks = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name 
                     WHERE feature = %s AND variant = %s AND event_type = 'click'",
                    $feature, $variant
                ));
                
                // Get conversions
                $conversions = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name 
                     WHERE feature = %s AND variant = %s AND event_type = 'conversion'",
                    $feature, $variant
                ));
                
                $stats[$feature][$variant] = array(
                    'impressions' => intval($impressions),
                    'clicks' => intval($clicks),
                    'conversions' => intval($conversions),
                    'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                    'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0
                );
            }
        }
        
        return $stats;
    }
}

// Initialize
add_action('init', array('SpiralEngine_Upgrade_Prompts', 'get_instance'), 15);

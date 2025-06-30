<?php
// includes/frontend/class-spiralengine-shortcodes.php

/**
 * SpiralEngine Shortcodes Class
 *
 * Handles all frontend shortcodes for the plugin
 *
 * @package    SpiralEngine
 * @subpackage SpiralEngine/includes/frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpiralEngine_Shortcodes {
    
    /**
     * Plugin version
     *
     * @var string
     */
    private $version;
    
    /**
     * Database instance
     *
     * @var SpiralEngine_Database
     */
    private $db;
    
    /**
     * Registered shortcodes
     *
     * @var array
     */
    private $shortcodes = array();
    
    /**
     * Constructor
     *
     * @param string $version Plugin version
     */
    public function __construct($version) {
        $this->version = $version;
        $this->db = SpiralEngine_Database::getInstance();
        $this->register_shortcodes();
    }
    
    /**
     * Register all shortcodes
     */
    private function register_shortcodes() {
        $shortcodes = array(
            // Main shortcodes
            'spiralengine' => 'render_main_shortcode',
            'spiralengine_dashboard' => 'render_dashboard',
            'spiralengine_assessment' => 'render_assessment',
            
            // Widget shortcodes
            'spiralengine_widget' => 'render_widget',
            'spiralengine_progress' => 'render_progress',
            'spiralengine_insights' => 'render_insights',
            
            // Chart shortcodes
            'spiralengine_chart' => 'render_chart',
            'spiralengine_mood_chart' => 'render_mood_chart',
            'spiralengine_wellness_chart' => 'render_wellness_chart',
            
            // Form shortcodes
            'spiralengine_quick_checkin' => 'render_quick_checkin',
            'spiralengine_journal' => 'render_journal',
            'spiralengine_goal_tracker' => 'render_goal_tracker',
            
            // Display shortcodes
            'spiralengine_achievements' => 'render_achievements',
            'spiralengine_streak' => 'render_streak',
            'spiralengine_quote' => 'render_daily_quote',
            
            // AI shortcodes
            'spiralengine_ai_chat' => 'render_ai_chat',
            'spiralengine_ai_insights' => 'render_ai_insights',
            'spiralengine_predictions' => 'render_predictions',
            
            // Member shortcodes
            'spiralengine_member_only' => 'render_member_content',
            'spiralengine_login_form' => 'render_login_form',
            'spiralengine_register_form' => 'render_register_form'
        );
        
        foreach ($shortcodes as $tag => $callback) {
            add_shortcode($tag, array($this, $callback));
            $this->shortcodes[$tag] = $callback;
        }
        
        // Allow extensions to add shortcodes
        do_action('spiralengine_register_shortcodes', $this);
    }
    
    /**
     * Main SpiralEngine shortcode
     *
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string Rendered output
     */
    public function render_main_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'type' => 'dashboard',
            'id' => '',
            'class' => '',
            'style' => ''
        ), $atts, 'spiralengine');
        
        // Enqueue required assets
        $this->enqueue_shortcode_assets('main');
        
        // Route to specific renderer based on type
        switch ($atts['type']) {
            case 'dashboard':
                return $this->render_dashboard($atts);
                
            case 'assessment':
                return $this->render_assessment($atts);
                
            case 'widget':
                return $this->render_widget($atts);
                
            default:
                return $this->render_custom_type($atts['type'], $atts, $content);
        }
    }
    
    /**
     * Render dashboard shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered dashboard
     */
    public function render_dashboard($atts) {
        $atts = shortcode_atts(array(
            'layout' => 'grid',
            'columns' => '3',
            'widgets' => '',
            'class' => '',
            'membership' => ''
        ), $atts, 'spiralengine_dashboard');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Enqueue dashboard assets
        $this->enqueue_shortcode_assets('dashboard');
        
        // Get user's dashboard configuration
        $user_id = get_current_user_id();
        $dashboard_config = $this->get_dashboard_config($user_id, $atts);
        
        // Start output buffering
        ob_start();
        
        // Load dashboard template
        $template = $this->get_template('dashboard', $atts['layout']);
        if ($template) {
            include $template;
        } else {
            $this->render_fallback_dashboard($dashboard_config);
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render assessment shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered assessment
     */
    public function render_assessment($atts) {
        $atts = shortcode_atts(array(
            'type' => 'mood',
            'id' => '',
            'title' => '',
            'description' => '',
            'questions' => '',
            'class' => '',
            'redirect' => '',
            'save_progress' => 'yes',
            'show_results' => 'yes',
            'membership' => ''
        ), $atts, 'spiralengine_assessment');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Enqueue assessment assets
        $this->enqueue_shortcode_assets('assessment');
        
        // Generate unique ID if not provided
        if (empty($atts['id'])) {
            $atts['id'] = 'spiralengine-assessment-' . uniqid();
        }
        
        // Get assessment configuration
        $assessment = $this->get_assessment_config($atts['type'], $atts);
        
        // Start output buffering
        ob_start();
        ?>
        <div id="<?php echo esc_attr($atts['id']); ?>" 
             class="spiralengine-assessment spiralengine-assessment-<?php echo esc_attr($atts['type']); ?> <?php echo esc_attr($atts['class']); ?>"
             data-assessment-type="<?php echo esc_attr($atts['type']); ?>"
             data-save-progress="<?php echo esc_attr($atts['save_progress']); ?>"
             data-show-results="<?php echo esc_attr($atts['show_results']); ?>">
            
            <?php if ($assessment['title']): ?>
                <h3 class="spiralengine-assessment-title"><?php echo esc_html($assessment['title']); ?></h3>
            <?php endif; ?>
            
            <?php if ($assessment['description']): ?>
                <p class="spiralengine-assessment-description"><?php echo wp_kses_post($assessment['description']); ?></p>
            <?php endif; ?>
            
            <form class="spiralengine-assessment-form" data-assessment-id="<?php echo esc_attr($atts['id']); ?>">
                <?php wp_nonce_field('spiralengine_assessment', 'assessment_nonce'); ?>
                <input type="hidden" name="assessment_type" value="<?php echo esc_attr($atts['type']); ?>">
                
                <div class="spiralengine-assessment-questions">
                    <?php $this->render_assessment_questions($assessment['questions']); ?>
                </div>
                
                <div class="spiralengine-assessment-actions">
                    <button type="submit" class="spiralengine-button spiralengine-button-primary">
                        <?php _e('Submit Assessment', 'spiral-engine'); ?>
                    </button>
                </div>
            </form>
            
            <div class="spiralengine-assessment-results" style="display: none;"></div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render widget shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered widget
     */
    public function render_widget($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'type' => '',
            'title' => '',
            'class' => '',
            'refresh' => '0',
            'collapsible' => 'no',
            'membership' => ''
        ), $atts, 'spiralengine_widget');
        
        // Widget ID is required
        if (empty($atts['id']) && empty($atts['type'])) {
            return '<p class="spiralengine-error">' . __('Widget ID or type is required.', 'spiral-engine') . '</p>';
        }
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Enqueue widget assets
        $this->enqueue_shortcode_assets('widget');
        
        // Get widget data
        $widget_id = !empty($atts['id']) ? $atts['id'] : $atts['type'];
        $widget_data = $this->get_widget_data($widget_id, get_current_user_id());
        
        if (!$widget_data) {
            return '<p class="spiralengine-error">' . __('Widget not found.', 'spiral-engine') . '</p>';
        }
        
        // Merge attributes with widget data
        $widget = array_merge($widget_data, array_filter($atts));
        
        // Start output buffering
        ob_start();
        
        // Load widget template
        $template = $this->get_template('widget', $widget['type']);
        if ($template) {
            include $template;
        } else {
            $this->render_generic_widget($widget);
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render progress shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered progress
     */
    public function render_progress($atts) {
        $atts = shortcode_atts(array(
            'type' => 'overall',
            'period' => '30',
            'chart' => 'line',
            'height' => '300',
            'class' => '',
            'membership' => ''
        ), $atts, 'spiralengine_progress');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Enqueue chart assets
        $this->enqueue_shortcode_assets('chart');
        
        // Get progress data
        $progress_data = $this->get_progress_data(get_current_user_id(), $atts['type'], $atts['period']);
        
        // Generate unique ID
        $chart_id = 'spiralengine-progress-' . uniqid();
        
        ob_start();
        ?>
        <div class="spiralengine-progress-chart <?php echo esc_attr($atts['class']); ?>">
            <canvas id="<?php echo esc_attr($chart_id); ?>" 
                    height="<?php echo esc_attr($atts['height']); ?>"
                    data-chart-type="<?php echo esc_attr($atts['chart']); ?>"
                    data-chart-data='<?php echo json_encode($progress_data); ?>'></canvas>
        </div>
        <script>
            jQuery(document).ready(function($) {
                SpiralEngine.Charts.renderProgress('<?php echo esc_js($chart_id); ?>');
            });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render insights shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered insights
     */
    public function render_insights($atts) {
        $atts = shortcode_atts(array(
            'type' => 'recent',
            'count' => '5',
            'category' => '',
            'layout' => 'list',
            'class' => '',
            'membership' => 'explorer'
        ), $atts, 'spiralengine_insights');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Get insights
        $insights = $this->get_user_insights(get_current_user_id(), $atts);
        
        if (empty($insights)) {
            return '<p class="spiralengine-no-insights">' . __('No insights available yet. Complete more assessments to see personalized insights.', 'spiral-engine') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="spiralengine-insights spiralengine-insights-<?php echo esc_attr($atts['layout']); ?> <?php echo esc_attr($atts['class']); ?>">
            <?php if ($atts['layout'] === 'grid'): ?>
                <div class="spiralengine-insights-grid">
                    <?php foreach ($insights as $insight): ?>
                        <?php $this->render_insight_card($insight); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <ul class="spiralengine-insights-list">
                    <?php foreach ($insights as $insight): ?>
                        <?php $this->render_insight_item($insight); ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render chart shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered chart
     */
    public function render_chart($atts) {
        $atts = shortcode_atts(array(
            'type' => 'line',
            'data' => '',
            'labels' => '',
            'title' => '',
            'height' => '400',
            'width' => '',
            'colors' => '',
            'class' => '',
            'id' => '',
            'membership' => ''
        ), $atts, 'spiralengine_chart');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Enqueue chart assets
        $this->enqueue_shortcode_assets('chart');
        
        // Generate unique ID if not provided
        if (empty($atts['id'])) {
            $atts['id'] = 'spiralengine-chart-' . uniqid();
        }
        
        // Process chart data
        $chart_config = $this->process_chart_data($atts);
        
        ob_start();
        ?>
        <div class="spiralengine-chart-container <?php echo esc_attr($atts['class']); ?>">
            <?php if ($atts['title']): ?>
                <h4 class="spiralengine-chart-title"><?php echo esc_html($atts['title']); ?></h4>
            <?php endif; ?>
            <canvas id="<?php echo esc_attr($atts['id']); ?>" 
                    height="<?php echo esc_attr($atts['height']); ?>"
                    <?php if ($atts['width']): ?>width="<?php echo esc_attr($atts['width']); ?>"<?php endif; ?>></canvas>
        </div>
        <script>
            jQuery(document).ready(function($) {
                var config = <?php echo json_encode($chart_config); ?>;
                SpiralEngine.Charts.render('<?php echo esc_js($atts['id']); ?>', config);
            });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render mood chart shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered mood chart
     */
    public function render_mood_chart($atts) {
        $atts = shortcode_atts(array(
            'days' => '7',
            'type' => 'line',
            'height' => '300',
            'class' => '',
            'membership' => ''
        ), $atts, 'spiralengine_mood_chart');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Get mood data
        $mood_data = $this->get_mood_data(get_current_user_id(), $atts['days']);
        
        // Use the chart shortcode to render
        $chart_atts = array(
            'type' => $atts['type'],
            'data' => json_encode($mood_data['data']),
            'labels' => json_encode($mood_data['labels']),
            'title' => sprintf(__('Mood Tracking - Last %d Days', 'spiral-engine'), $atts['days']),
            'height' => $atts['height'],
            'colors' => '#4a90e2,#7ed321,#f5a623,#d0021b',
            'class' => 'spiralengine-mood-chart ' . $atts['class']
        );
        
        return $this->render_chart($chart_atts);
    }
    
    /**
     * Render wellness chart shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered wellness chart
     */
    public function render_wellness_chart($atts) {
        $atts = shortcode_atts(array(
            'categories' => 'physical,mental,emotional,social,spiritual',
            'period' => 'current',
            'type' => 'radar',
            'height' => '400',
            'class' => '',
            'membership' => 'explorer'
        ), $atts, 'spiralengine_wellness_chart');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Get wellness data
        $categories = explode(',', $atts['categories']);
        $wellness_data = $this->get_wellness_data(get_current_user_id(), $categories, $atts['period']);
        
        // Use the chart shortcode to render
        $chart_atts = array(
            'type' => $atts['type'],
            'data' => json_encode($wellness_data['data']),
            'labels' => json_encode($wellness_data['labels']),
            'title' => __('Wellness Balance', 'spiral-engine'),
            'height' => $atts['height'],
            'colors' => '#4a90e2',
            'class' => 'spiralengine-wellness-chart ' . $atts['class']
        );
        
        return $this->render_chart($chart_atts);
    }
    
    /**
     * Render quick check-in shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered check-in form
     */
    public function render_quick_checkin($atts) {
        $atts = shortcode_atts(array(
            'type' => 'mood',
            'redirect' => '',
            'show_history' => 'yes',
            'class' => '',
            'membership' => ''
        ), $atts, 'spiralengine_quick_checkin');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Enqueue assets
        $this->enqueue_shortcode_assets('checkin');
        
        // Get last check-in
        $last_checkin = $this->get_last_checkin(get_current_user_id(), $atts['type']);
        
        ob_start();
        ?>
        <div class="spiralengine-quick-checkin <?php echo esc_attr($atts['class']); ?>">
            <form class="spiralengine-checkin-form" data-checkin-type="<?php echo esc_attr($atts['type']); ?>">
                <?php wp_nonce_field('spiralengine_checkin', 'checkin_nonce'); ?>
                <input type="hidden" name="checkin_type" value="<?php echo esc_attr($atts['type']); ?>">
                
                <?php if ($atts['type'] === 'mood'): ?>
                    <div class="spiralengine-mood-selector">
                        <p><?php _e('How are you feeling right now?', 'spiral-engine'); ?></p>
                        <div class="spiralengine-mood-options">
                            <?php $this->render_mood_options(); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php do_action('spiralengine_checkin_fields_' . $atts['type'], $atts); ?>
                <?php endif; ?>
                
                <div class="spiralengine-checkin-notes">
                    <label for="checkin-notes"><?php _e('Any notes? (optional)', 'spiral-engine'); ?></label>
                    <textarea id="checkin-notes" name="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="spiralengine-button spiralengine-button-primary">
                    <?php _e('Save Check-in', 'spiral-engine'); ?>
                </button>
            </form>
            
            <?php if ($atts['show_history'] === 'yes' && $last_checkin): ?>
                <div class="spiralengine-checkin-history">
                    <p class="spiralengine-last-checkin">
                        <?php printf(
                            __('Last check-in: %s', 'spiral-engine'),
                            human_time_diff(strtotime($last_checkin->created_at), current_time('timestamp')) . ' ' . __('ago', 'spiral-engine')
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render journal shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered journal
     */
    public function render_journal($atts) {
        $atts = shortcode_atts(array(
            'prompts' => 'yes',
            'private' => 'yes',
            'categories' => '',
            'show_history' => 'yes',
            'entries_per_page' => '10',
            'class' => '',
            'membership' => ''
        ), $atts, 'spiralengine_journal');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Enqueue assets
        $this->enqueue_shortcode_assets('journal');
        
        // Get journal data
        $user_id = get_current_user_id();
        $entries = $this->get_journal_entries($user_id, $atts);
        $prompt = $atts['prompts'] === 'yes' ? $this->get_daily_prompt() : null;
        
        ob_start();
        
        // Load journal template
        $template = $this->get_template('journal', 'default');
        if ($template) {
            include $template;
        } else {
            $this->render_fallback_journal($entries, $prompt, $atts);
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render goal tracker shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered goal tracker
     */
    public function render_goal_tracker($atts) {
        $atts = shortcode_atts(array(
            'categories' => 'health,personal,professional',
            'show_completed' => 'no',
            'layout' => 'list',
            'class' => '',
            'membership' => ''
        ), $atts, 'spiralengine_goal_tracker');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Enqueue assets
        $this->enqueue_shortcode_assets('goals');
        
        // Get goals
        $user_id = get_current_user_id();
        $goals = $this->get_user_goals($user_id, $atts);
        
        ob_start();
        ?>
        <div class="spiralengine-goal-tracker spiralengine-layout-<?php echo esc_attr($atts['layout']); ?> <?php echo esc_attr($atts['class']); ?>">
            <div class="spiralengine-goals-header">
                <h3><?php _e('Your Goals', 'spiral-engine'); ?></h3>
                <button class="spiralengine-button spiralengine-add-goal" data-categories="<?php echo esc_attr($atts['categories']); ?>">
                    <?php _e('Add New Goal', 'spiral-engine'); ?>
                </button>
            </div>
            
            <?php if (empty($goals)): ?>
                <p class="spiralengine-no-goals"><?php _e('You haven\'t set any goals yet. Click "Add New Goal" to get started!', 'spiral-engine'); ?></p>
            <?php else: ?>
                <div class="spiralengine-goals-container">
                    <?php foreach ($goals as $goal): ?>
                        <?php $this->render_goal_item($goal, $atts['layout']); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render achievements shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered achievements
     */
    public function render_achievements($atts) {
        $atts = shortcode_atts(array(
            'type' => 'earned',
            'category' => '',
            'layout' => 'grid',
            'show_progress' => 'yes',
            'class' => '',
            'membership' => ''
        ), $atts, 'spiralengine_achievements');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Get achievements
        $user_id = get_current_user_id();
        $achievements = $this->get_user_achievements($user_id, $atts);
        
        ob_start();
        ?>
        <div class="spiralengine-achievements spiralengine-achievements-<?php echo esc_attr($atts['layout']); ?> <?php echo esc_attr($atts['class']); ?>">
            <?php if ($atts['layout'] === 'grid'): ?>
                <div class="spiralengine-achievements-grid">
                    <?php foreach ($achievements as $achievement): ?>
                        <?php $this->render_achievement_card($achievement, $atts['show_progress']); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <ul class="spiralengine-achievements-list">
                    <?php foreach ($achievements as $achievement): ?>
                        <?php $this->render_achievement_item($achievement, $atts['show_progress']); ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render streak shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered streak
     */
    public function render_streak($atts) {
        $atts = shortcode_atts(array(
            'type' => 'current',
            'show_calendar' => 'no',
            'class' => '',
            'membership' => ''
        ), $atts, 'spiralengine_streak');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Get streak data
        $user_id = get_current_user_id();
        $streak_data = $this->get_streak_data($user_id);
        
        ob_start();
        ?>
        <div class="spiralengine-streak <?php echo esc_attr($atts['class']); ?>">
            <div class="spiralengine-streak-display">
                <span class="spiralengine-streak-number"><?php echo esc_html($streak_data['current']); ?></span>
                <span class="spiralengine-streak-label"><?php echo esc_html(_n('day', 'days', $streak_data['current'], 'spiral-engine')); ?></span>
            </div>
            
            <?php if ($streak_data['current'] > 0): ?>
                <p class="spiralengine-streak-message">
                    <?php 
                    if ($streak_data['current'] >= $streak_data['best']) {
                        _e('🔥 New personal best!', 'spiral-engine');
                    } else {
                        printf(__('Keep going! Your best streak is %d days.', 'spiral-engine'), $streak_data['best']);
                    }
                    ?>
                </p>
            <?php endif; ?>
            
            <?php if ($atts['show_calendar'] === 'yes'): ?>
                <div class="spiralengine-streak-calendar">
                    <?php $this->render_streak_calendar($streak_data['calendar']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render daily quote shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered quote
     */
    public function render_daily_quote($atts) {
        $atts = shortcode_atts(array(
            'category' => 'wellness',
            'refresh' => 'daily',
            'show_author' => 'yes',
            'class' => ''
        ), $atts, 'spiralengine_quote');
        
        // Get quote
        $quote = $this->get_daily_quote($atts['category'], $atts['refresh']);
        
        if (!$quote) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="spiralengine-quote <?php echo esc_attr($atts['class']); ?>">
            <blockquote>
                <p><?php echo esc_html($quote['text']); ?></p>
                <?php if ($atts['show_author'] === 'yes' && !empty($quote['author'])): ?>
                    <cite>— <?php echo esc_html($quote['author']); ?></cite>
                <?php endif; ?>
            </blockquote>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render AI chat shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered AI chat
     */
    public function render_ai_chat($atts) {
        $atts = shortcode_atts(array(
            'mode' => 'companion',
            'height' => '400',
            'welcome' => '',
            'context' => 'general',
            'class' => '',
            'membership' => 'navigator'
        ), $atts, 'spiralengine_ai_chat');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Check AI features enabled
        if (!get_option('spiralengine_ai_enabled', true)) {
            return '<p class="spiralengine-notice">' . __('AI features are currently unavailable.', 'spiral-engine') . '</p>';
        }
        
        // Enqueue AI assets
        $this->enqueue_shortcode_assets('ai');
        
        // Generate chat ID
        $chat_id = 'spiralengine-ai-chat-' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($chat_id); ?>" 
             class="spiralengine-ai-chat spiralengine-ai-<?php echo esc_attr($atts['mode']); ?> <?php echo esc_attr($atts['class']); ?>"
             style="height: <?php echo esc_attr($atts['height']); ?>px;"
             data-mode="<?php echo esc_attr($atts['mode']); ?>"
             data-context="<?php echo esc_attr($atts['context']); ?>">
            
            <div class="spiralengine-ai-header">
                <h4><?php _e('AI Wellness Companion', 'spiral-engine'); ?></h4>
                <span class="spiralengine-ai-status" data-status="ready">
                    <span class="spiralengine-status-indicator"></span>
                    <?php _e('Ready', 'spiral-engine'); ?>
                </span>
            </div>
            
            <div class="spiralengine-ai-messages">
                <?php if ($atts['welcome']): ?>
                    <div class="spiralengine-ai-message spiralengine-ai-assistant">
                        <p><?php echo esc_html($atts['welcome']); ?></p>
                    </div>
                <?php else: ?>
                    <div class="spiralengine-ai-message spiralengine-ai-assistant">
                        <p><?php _e('Hello! I\'m here to support you on your wellness journey. How can I help you today?', 'spiral-engine'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="spiralengine-ai-input">
                <form class="spiralengine-ai-form">
                    <input type="text" 
                           class="spiralengine-ai-input-field" 
                           placeholder="<?php esc_attr_e('Type your message...', 'spiral-engine'); ?>"
                           autocomplete="off">
                    <button type="submit" class="spiralengine-ai-send">
                        <?php _e('Send', 'spiral-engine'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render AI insights shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered AI insights
     */
    public function render_ai_insights($atts) {
        $atts = shortcode_atts(array(
            'type' => 'personalized',
            'count' => '3',
            'refresh' => 'daily',
            'class' => '',
            'membership' => 'navigator'
        ), $atts, 'spiralengine_ai_insights');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Get AI insights
        $insights = $this->get_ai_insights(get_current_user_id(), $atts);
        
        if (empty($insights)) {
            return '<p class="spiralengine-no-insights">' . __('AI is analyzing your data. Check back soon for personalized insights!', 'spiral-engine') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="spiralengine-ai-insights <?php echo esc_attr($atts['class']); ?>">
            <?php foreach ($insights as $insight): ?>
                <div class="spiralengine-ai-insight">
                    <div class="spiralengine-insight-icon">
                        <?php echo $this->get_insight_icon($insight['type']); ?>
                    </div>
                    <div class="spiralengine-insight-content">
                        <h4><?php echo esc_html($insight['title']); ?></h4>
                        <p><?php echo wp_kses_post($insight['content']); ?></p>
                        <?php if (!empty($insight['action'])): ?>
                            <a href="<?php echo esc_url($insight['action']['url']); ?>" class="spiralengine-insight-action">
                                <?php echo esc_html($insight['action']['text']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render predictions shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered predictions
     */
    public function render_predictions($atts) {
        $atts = shortcode_atts(array(
            'type' => 'wellness',
            'period' => '7',
            'show_confidence' => 'yes',
            'class' => '',
            'membership' => 'voyager'
        ), $atts, 'spiralengine_predictions');
        
        // Check access
        if (!$this->check_access($atts['membership'])) {
            return $this->render_access_denied($atts['membership']);
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        // Get predictions
        $predictions = $this->get_ai_predictions(get_current_user_id(), $atts);
        
        if (empty($predictions)) {
            return '<p class="spiralengine-no-predictions">' . __('Not enough data for predictions yet. Keep tracking your wellness!', 'spiral-engine') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="spiralengine-predictions <?php echo esc_attr($atts['class']); ?>">
            <h3><?php printf(__('%d-Day Wellness Forecast', 'spiral-engine'), $atts['period']); ?></h3>
            
            <?php foreach ($predictions as $prediction): ?>
                <div class="spiralengine-prediction">
                    <h4><?php echo esc_html($prediction['category']); ?></h4>
                    <div class="spiralengine-prediction-graph">
                        <?php $this->render_prediction_graph($prediction['data']); ?>
                    </div>
                    <?php if ($atts['show_confidence'] === 'yes'): ?>
                        <p class="spiralengine-prediction-confidence">
                            <?php printf(__('Confidence: %d%%', 'spiral-engine'), $prediction['confidence']); ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($prediction['recommendation'])): ?>
                        <p class="spiralengine-prediction-recommendation">
                            <?php echo esc_html($prediction['recommendation']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render member-only content shortcode
     *
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string Rendered content
     */
    public function render_member_content($atts, $content = null) {
        $atts = shortcode_atts(array(
            'level' => '',
            'message' => '',
            'show_upgrade' => 'yes'
        ), $atts, 'spiralengine_member_only');
        
        // Check if user has access
        if ($this->check_access($atts['level'])) {
            return do_shortcode($content);
        }
        
        // Show restricted message
        ob_start();
        ?>
        <div class="spiralengine-restricted-content">
            <?php if ($atts['message']): ?>
                <p><?php echo esc_html($atts['message']); ?></p>
            <?php else: ?>
                <p><?php _e('This content is available to members only.', 'spiral-engine'); ?></p>
            <?php endif; ?>
            
            <?php if ($atts['show_upgrade'] === 'yes'): ?>
                <a href="<?php echo esc_url(get_permalink(get_option('spiralengine_upgrade_page'))); ?>" class="spiralengine-button">
                    <?php _e('Upgrade Your Membership', 'spiral-engine'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render login form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered login form
     */
    public function render_login_form($atts) {
        $atts = shortcode_atts(array(
            'redirect' => '',
            'register_link' => 'yes',
            'forgot_link' => 'yes',
            'class' => ''
        ), $atts, 'spiralengine_login_form');
        
        // Already logged in
        if (is_user_logged_in()) {
            return '<p class="spiralengine-logged-in">' . 
                   sprintf(__('You are already logged in. <a href="%s">Go to dashboard</a>', 'spiral-engine'), 
                   esc_url(get_permalink(get_option('spiralengine_dashboard_page')))) . 
                   '</p>';
        }
        
        // Set redirect URL
        if (empty($atts['redirect'])) {
            $atts['redirect'] = get_permalink(get_option('spiralengine_dashboard_page'));
        }
        
        ob_start();
        ?>
        <div class="spiralengine-login-form <?php echo esc_attr($atts['class']); ?>">
            <form method="post" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>">
                <?php wp_nonce_field('spiralengine_login', 'spiralengine_login_nonce'); ?>
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                
                <div class="spiralengine-form-field">
                    <label for="spiralengine-username"><?php _e('Username or Email', 'spiral-engine'); ?></label>
                    <input type="text" name="log" id="spiralengine-username" required>
                </div>
                
                <div class="spiralengine-form-field">
                    <label for="spiralengine-password"><?php _e('Password', 'spiral-engine'); ?></label>
                    <input type="password" name="pwd" id="spiralengine-password" required>
                </div>
                
                <div class="spiralengine-form-field spiralengine-remember">
                    <label>
                        <input type="checkbox" name="rememberme" value="forever">
                        <?php _e('Remember Me', 'spiral-engine'); ?>
                    </label>
                </div>
                
                <button type="submit" class="spiralengine-button spiralengine-button-primary spiralengine-button-full">
                    <?php _e('Log In', 'spiral-engine'); ?>
                </button>
                
                <div class="spiralengine-form-links">
                    <?php if ($atts['register_link'] === 'yes'): ?>
                        <a href="<?php echo esc_url(wp_registration_url()); ?>">
                            <?php _e('Create Account', 'spiral-engine'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($atts['forgot_link'] === 'yes'): ?>
                        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">
                            <?php _e('Forgot Password?', 'spiral-engine'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render register form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered register form
     */
    public function render_register_form($atts) {
        $atts = shortcode_atts(array(
            'redirect' => '',
            'membership' => 'discovery',
            'show_terms' => 'yes',
            'class' => ''
        ), $atts, 'spiralengine_register_form');
        
        // Already logged in
        if (is_user_logged_in()) {
            return '<p class="spiralengine-logged-in">' . 
                   __('You already have an account.', 'spiral-engine') . 
                   '</p>';
        }
        
        // Check if registration is enabled
        if (!get_option('users_can_register')) {
            return '<p class="spiralengine-registration-closed">' . 
                   __('Registration is currently closed.', 'spiral-engine') . 
                   '</p>';
        }
        
        // Enqueue registration assets
        $this->enqueue_shortcode_assets('registration');
        
        ob_start();
        ?>
        <div class="spiralengine-register-form <?php echo esc_attr($atts['class']); ?>">
            <form method="post" class="spiralengine-registration">
                <?php wp_nonce_field('spiralengine_register', 'spiralengine_register_nonce'); ?>
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                <input type="hidden" name="membership_level" value="<?php echo esc_attr($atts['membership']); ?>">
                
                <div class="spiralengine-form-field">
                    <label for="spiralengine-reg-username"><?php _e('Username', 'spiral-engine'); ?></label>
                    <input type="text" name="user_login" id="spiralengine-reg-username" required>
                </div>
                
                <div class="spiralengine-form-field">
                    <label for="spiralengine-reg-email"><?php _e('Email Address', 'spiral-engine'); ?></label>
                    <input type="email" name="user_email" id="spiralengine-reg-email" required>
                </div>
                
                <div class="spiralengine-form-field">
                    <label for="spiralengine-reg-password"><?php _e('Password', 'spiral-engine'); ?></label>
                    <input type="password" name="user_pass" id="spiralengine-reg-password" required>
                    <span class="spiralengine-password-strength"></span>
                </div>
                
                <div class="spiralengine-form-field">
                    <label for="spiralengine-reg-password2"><?php _e('Confirm Password', 'spiral-engine'); ?></label>
                    <input type="password" name="user_pass2" id="spiralengine-reg-password2" required>
                </div>
                
                <?php if ($atts['show_terms'] === 'yes'): ?>
                    <div class="spiralengine-form-field spiralengine-terms">
                        <label>
                            <input type="checkbox" name="agree_terms" value="1" required>
                            <?php printf(
                                __('I agree to the <a href="%s" target="_blank">Terms of Service</a> and <a href="%s" target="_blank">Privacy Policy</a>', 'spiral-engine'),
                                esc_url(get_permalink(get_option('spiralengine_terms_page'))),
                                esc_url(get_permalink(get_option('spiralengine_privacy_page')))
                            ); ?>
                        </label>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="spiralengine-button spiralengine-button-primary spiralengine-button-full">
                    <?php _e('Create Account', 'spiral-engine'); ?>
                </button>
                
                <p class="spiralengine-login-link">
                    <?php printf(
                        __('Already have an account? <a href="%s">Log in</a>', 'spiral-engine'),
                        esc_url(wp_login_url())
                    ); ?>
                </p>
            </form>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Check user access based on membership
     *
     * @param string $required_membership Required membership level
     * @return bool Has access
     */
    private function check_access($required_membership) {
        // No restriction
        if (empty($required_membership)) {
            return true;
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Get user membership
        $user_membership = $this->get_user_membership_level(get_current_user_id());
        
        // Define membership hierarchy
        $hierarchy = array(
            'discovery' => 0,
            'explorer' => 1,
            'navigator' => 2,
            'voyager' => 3
        );
        
        // Check if user meets requirement
        $required_level = isset($hierarchy[$required_membership]) ? $hierarchy[$required_membership] : 0;
        $user_level = isset($hierarchy[$user_membership]) ? $hierarchy[$user_membership] : 0;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Get user membership level
     *
     * @param int $user_id User ID
     * @return string Membership level
     */
    private function get_user_membership_level($user_id) {
        // Check MemberPress
        if (function_exists('mepr_get_user_memberships')) {
            $memberships = mepr_get_user_memberships($user_id);
            if (!empty($memberships)) {
                // Return highest level membership
                foreach (array('voyager', 'navigator', 'explorer', 'discovery') as $level) {
                    foreach ($memberships as $membership) {
                        if (stripos($membership->post_title, $level) !== false) {
                            return $level;
                        }
                    }
                }
            }
        }
        
        // Fallback to user meta
        $level = get_user_meta($user_id, 'spiralengine_membership_level', true);
        
        return $level ?: 'discovery';
    }
    
    /**
     * Enqueue shortcode-specific assets
     *
     * @param string $type Asset type
     */
    private function enqueue_shortcode_assets($type) {
        // Always enqueue base frontend assets
        wp_enqueue_style('spiralengine-frontend');
        wp_enqueue_script('spiralengine-frontend');
        
        // Type-specific assets
        switch ($type) {
            case 'dashboard':
                wp_enqueue_script('spiralengine-dashboard');
                wp_enqueue_style('spiralengine-dashboard');
                break;
                
            case 'assessment':
                wp_enqueue_script('spiralengine-assessment');
                wp_enqueue_style('spiralengine-assessment');
                break;
                
            case 'chart':
                wp_enqueue_script('spiralengine-charts');
                break;
                
            case 'ai':
                wp_enqueue_script('spiralengine-ai');
                wp_enqueue_style('spiralengine-ai');
                break;
                
            case 'journal':
                wp_enqueue_script('spiralengine-journal');
                wp_enqueue_style('spiralengine-journal');
                break;
                
            case 'goals':
                wp_enqueue_script('spiralengine-goals');
                break;
        }
    }
    
    /**
     * Get template path
     *
     * @param string $template Template name
     * @param string $type Template type
     * @return string|false Template path or false
     */
    private function get_template($template, $type = 'default') {
        $templates = array(
            // Theme override
            get_stylesheet_directory() . '/spiralengine/' . $template . '-' . $type . '.php',
            get_stylesheet_directory() . '/spiralengine/' . $template . '.php',
            
            // Parent theme override
            get_template_directory() . '/spiralengine/' . $template . '-' . $type . '.php',
            get_template_directory() . '/spiralengine/' . $template . '.php',
            
            // Plugin templates
            SPIRALENGINE_PLUGIN_DIR . 'templates/shortcodes/' . $template . '-' . $type . '.php',
            SPIRALENGINE_PLUGIN_DIR . 'templates/shortcodes/' . $template . '.php'
        );
        
        foreach ($templates as $template_path) {
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        
        return false;
    }
    
    /**
     * Render access denied message
     *
     * @param string $required_level Required membership level
     * @return string Rendered message
     */
    private function render_access_denied($required_level) {
        ob_start();
        ?>
        <div class="spiralengine-access-denied">
            <p><?php printf(
                __('This content requires %s membership or higher.', 'spiral-engine'),
                ucfirst($required_level)
            ); ?></p>
            <a href="<?php echo esc_url(get_permalink(get_option('spiralengine_upgrade_page'))); ?>" class="spiralengine-button">
                <?php _e('Upgrade Membership', 'spiral-engine'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render login prompt
     *
     * @return string Rendered prompt
     */
    private function render_login_prompt() {
        ob_start();
        ?>
        <div class="spiralengine-login-prompt">
            <p><?php _e('Please log in to access this content.', 'spiral-engine'); ?></p>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="spiralengine-button">
                <?php _e('Log In', 'spiral-engine'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Helper method to render custom type
     */
    private function render_custom_type($type, $atts, $content) {
        // Allow extensions to handle custom types
        $output = apply_filters('spiralengine_shortcode_' . $type, '', $atts, $content);
        
        if (empty($output)) {
            $output = '<p class="spiralengine-error">' . 
                     sprintf(__('Unknown SpiralEngine type: %s', 'spiral-engine'), esc_html($type)) . 
                     '</p>';
        }
        
        return $output;
    }
    
    // Additional helper methods for specific shortcode functionality
    private function get_dashboard_config($user_id, $atts) {
        // Implementation for dashboard configuration
        return array();
    }
    
    private function render_fallback_dashboard($config) {
        // Fallback dashboard rendering
        echo '<div class="spiralengine-dashboard-fallback">';
        echo '<p>' . __('Dashboard is being prepared...', 'spiral-engine') . '</p>';
        echo '</div>';
    }
    
    private function get_assessment_config($type, $atts) {
        // Implementation for assessment configuration
        return array(
            'title' => $atts['title'] ?: ucfirst($type) . ' Assessment',
            'description' => $atts['description'],
            'questions' => array()
        );
    }
    
    private function render_assessment_questions($questions) {
        // Implementation for rendering assessment questions
        echo '<p>' . __('Assessment questions loading...', 'spiral-engine') . '</p>';
    }
    
    private function get_widget_data($widget_id, $user_id) {
        // Implementation for getting widget data
        return array(
            'type' => $widget_id,
            'title' => ucfirst($widget_id) . ' Widget',
            'content' => ''
        );
    }
    
    private function render_generic_widget($widget) {
        // Generic widget rendering
        echo '<div class="spiralengine-widget-generic">';
        echo '<h4>' . esc_html($widget['title']) . '</h4>';
        echo '<div class="spiralengine-widget-content">' . wp_kses_post($widget['content']) . '</div>';
        echo '</div>';
    }
    
    private function get_progress_data($user_id, $type, $period) {
        // Implementation for getting progress data
        return array(
            'labels' => array(),
            'datasets' => array()
        );
    }
    
    private function get_user_insights($user_id, $atts) {
        // Implementation for getting user insights
        return array();
    }
    
    private function render_insight_card($insight) {
        // Implementation for rendering insight card
    }
    
    private function render_insight_item($insight) {
        // Implementation for rendering insight item
    }
    
    private function process_chart_data($atts) {
        // Implementation for processing chart data
        return array(
            'type' => $atts['type'],
            'data' => array(),
            'options' => array()
        );
    }
    
    private function get_mood_data($user_id, $days) {
        // Implementation for getting mood data
        return array(
            'labels' => array(),
            'data' => array()
        );
    }
    
    private function get_wellness_data($user_id, $categories, $period) {
        // Implementation for getting wellness data
        return array(
            'labels' => $categories,
            'data' => array()
        );
    }
    
    private function get_last_checkin($user_id, $type) {
        // Implementation for getting last check-in
        return null;
    }
    
    private function render_mood_options() {
        // Implementation for rendering mood options
        $moods = array(
            'excellent' => '😊',
            'good' => '🙂',
            'okay' => '😐',
            'poor' => '😔',
            'terrible' => '😢'
        );
        
        foreach ($moods as $value => $emoji) {
            echo '<label class="spiralengine-mood-option">';
            echo '<input type="radio" name="mood" value="' . esc_attr($value) . '">';
            echo '<span class="spiralengine-mood-emoji">' . $emoji . '</span>';
            echo '<span class="spiralengine-mood-label">' . ucfirst($value) . '</span>';
            echo '</label>';
        }
    }
    
    private function get_journal_entries($user_id, $atts) {
        // Implementation for getting journal entries
        return array();
    }
    
    private function get_daily_prompt() {
        // Implementation for getting daily journal prompt
        return __('What are you grateful for today?', 'spiral-engine');
    }
    
    private function render_fallback_journal($entries, $prompt, $atts) {
        // Fallback journal rendering
        echo '<div class="spiralengine-journal-fallback">';
        echo '<p>' . __('Journal feature coming soon...', 'spiral-engine') . '</p>';
        echo '</div>';
    }
    
    private function get_user_goals($user_id, $atts) {
        // Implementation for getting user goals
        return array();
    }
    
    private function render_goal_item($goal, $layout) {
        // Implementation for rendering goal item
    }
    
    private function get_user_achievements($user_id, $atts) {
        // Implementation for getting user achievements
        return array();
    }
    
    private function render_achievement_card($achievement, $show_progress) {
        // Implementation for rendering achievement card
    }
    
    private function render_achievement_item($achievement, $show_progress) {
        // Implementation for rendering achievement item
    }
    
    private function get_streak_data($user_id) {
        // Implementation for getting streak data
        return array(
            'current' => 0,
            'best' => 0,
            'calendar' => array()
        );
    }
    
    private function render_streak_calendar($calendar_data) {
        // Implementation for rendering streak calendar
    }
    
    private function get_daily_quote($category, $refresh) {
        // Implementation for getting daily quote
        return array(
            'text' => 'The journey of a thousand miles begins with a single step.',
            'author' => 'Lao Tzu'
        );
    }
    
    private function get_ai_insights($user_id, $atts) {
        // Implementation for getting AI insights
        return array();
    }
    
    private function get_insight_icon($type) {
        // Implementation for getting insight icon
        return '<span class="dashicons dashicons-lightbulb"></span>';
    }
    
    private function get_ai_predictions($user_id, $atts) {
        // Implementation for getting AI predictions
        return array();
    }
    
    private function render_prediction_graph($data) {
        // Implementation for rendering prediction graph
    }
}

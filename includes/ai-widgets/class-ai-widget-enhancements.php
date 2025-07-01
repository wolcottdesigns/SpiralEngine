<?php
/**
 * SpiralEngine AI Widget Enhancements
 * 
 * @package    SpiralEngine
 * @subpackage AI/Widgets
 * @file       includes/ai-widgets/class-ai-widget-enhancements.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Widget Enhancements class
 * 
 * Adds AI-powered features to existing widgets
 */
class SpiralEngine_AI_Widget_Enhancements {
    
    /**
     * AI Service instance
     *
     * @var SpiralEngine_AI_Service
     */
    private $ai_service;
    
    /**
     * Pattern Analyzer instance
     *
     * @var SpiralEngine_Pattern_Analyzer
     */
    private $pattern_analyzer;
    
    /**
     * Insight Generator instance
     *
     * @var SpiralEngine_Insight_Generator
     */
    private $insight_generator;
    
    /**
     * Widget enhancement configurations
     *
     * @var array
     */
    private $widget_enhancements = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->ai_service = SpiralEngine_AI_Service::get_instance();
        $this->pattern_analyzer = new SpiralEngine_Pattern_Analyzer();
        $this->insight_generator = new SpiralEngine_Insight_Generator();
        
        $this->init_widget_enhancements();
        $this->init_hooks();
    }
    
    /**
     * Initialize widget enhancement configurations
     *
     * @return void
     */
    private function init_widget_enhancements() {
        $this->widget_enhancements = array(
            'trigger-tracker' => array(
                'name' => 'Trigger Tracker AI',
                'features' => array('trigger_prediction', 'pattern_detection', 'coping_suggestions'),
                'min_tier' => 'silver'
            ),
            'mood-tracker' => array(
                'name' => 'Mood Tracker AI',
                'features' => array('mood_prediction', 'trend_analysis', 'insights'),
                'min_tier' => 'silver'
            ),
            'coping-logger' => array(
                'name' => 'Coping Logger AI',
                'features' => array('effectiveness_prediction', 'skill_recommendations', 'progress_tracking'),
                'min_tier' => 'silver'
            ),
            'thought-challenger' => array(
                'name' => 'Thought Challenger AI',
                'features' => array('cognitive_analysis', 'reframe_suggestions', 'pattern_identification'),
                'min_tier' => 'gold'
            ),
            'medication-tracker' => array(
                'name' => 'Medication Tracker AI',
                'features' => array('adherence_analysis', 'effectiveness_tracking', 'reminder_optimization'),
                'min_tier' => 'gold'
            ),
            'goal-setting' => array(
                'name' => 'Goal Setting AI',
                'features' => array('goal_recommendations', 'milestone_predictions', 'obstacle_analysis'),
                'min_tier' => 'gold'
            ),
            'journal-entry' => array(
                'name' => 'Journal Entry AI',
                'features' => array('sentiment_analysis', 'theme_extraction', 'prompt_suggestions'),
                'min_tier' => 'silver'
            ),
            'sleep-tracker' => array(
                'name' => 'Sleep Tracker AI',
                'features' => array('sleep_quality_prediction', 'pattern_analysis', 'improvement_suggestions'),
                'min_tier' => 'silver'
            ),
            'emotion-wheel' => array(
                'name' => 'Emotion Wheel AI',
                'features' => array('emotion_patterns', 'trigger_correlations', 'emotional_insights'),
                'min_tier' => 'gold'
            ),
            'daily-checkin' => array(
                'name' => 'Daily Check-in AI',
                'features' => array('comprehensive_analysis', 'daily_predictions', 'personalized_tips'),
                'min_tier' => 'silver'
            )
        );
    }
    
    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Add AI features to widget outputs
        add_filter('spiralengine_widget_output', array($this, 'enhance_widget_output'), 10, 3);
        
        // Add AI analysis after episode creation
        add_action('spiralengine_after_episode_created', array($this, 'analyze_episode'), 10, 2);
        
        // Add AI suggestions to widget forms
        add_action('spiralengine_widget_form_after', array($this, 'add_ai_suggestions'), 10, 2);
        
        // AJAX handlers for real-time AI features
        add_action('wp_ajax_spiralengine_get_ai_suggestion', array($this, 'ajax_get_suggestion'));
        add_action('wp_ajax_spiralengine_analyze_input', array($this, 'ajax_analyze_input'));
        add_action('wp_ajax_spiralengine_predict_outcome', array($this, 'ajax_predict_outcome'));
    }
    
    /**
     * Enhance widget output with AI features
     *
     * @param string $output Widget output
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string Enhanced output
     */
    public function enhance_widget_output($output, $widget_id, $data) {
        // Check if widget has AI enhancements
        if (!isset($this->widget_enhancements[$widget_id])) {
            return $output;
        }
        
        // Check user permissions
        $user_id = get_current_user_id();
        $membership = new SpiralEngine_Membership($user_id);
        
        if (!$this->user_can_access_ai_features($membership->get_tier(), $this->widget_enhancements[$widget_id]['min_tier'])) {
            return $output;
        }
        
        // Add AI enhancement container
        $ai_content = '<div class="spiralengine-ai-enhancement" data-widget="' . esc_attr($widget_id) . '">';
        
        // Add feature-specific enhancements
        foreach ($this->widget_enhancements[$widget_id]['features'] as $feature) {
            $method = 'get_' . $feature . '_content';
            if (method_exists($this, $method)) {
                $ai_content .= $this->$method($widget_id, $data);
            }
        }
        
        $ai_content .= '</div>';
        
        // Insert AI content into widget output
        return $output . $ai_content;
    }
    
    /**
     * Analyze episode after creation
     *
     * @param int $episode_id Episode ID
     * @param array $episode_data Episode data
     * @return void
     */
    public function analyze_episode($episode_id, $episode_data) {
        // Check if AI analysis is enabled
        if (!get_option('spiralengine_ai_enabled', true)) {
            return;
        }
        
        // Check user permissions
        $membership = new SpiralEngine_Membership($episode_data['user_id']);
        if (!in_array($membership->get_tier(), array('silver', 'gold', 'platinum'))) {
            return;
        }
        
        // Perform AI analysis asynchronously
        wp_schedule_single_event(time() + 5, 'spiralengine_analyze_episode_ai', array($episode_id));
    }
    
    /**
     * Add AI suggestions to widget forms
     *
     * @param string $widget_id Widget ID
     * @param array $context Context data
     * @return void
     */
    public function add_ai_suggestions($widget_id, $context) {
        if (!isset($this->widget_enhancements[$widget_id])) {
            return;
        }
        
        $user_id = get_current_user_id();
        $membership = new SpiralEngine_Membership($user_id);
        
        if (!$this->user_can_access_ai_features($membership->get_tier(), $this->widget_enhancements[$widget_id]['min_tier'])) {
            return;
        }
        
        ?>
        <div class="spiralengine-ai-suggestions" id="ai-suggestions-<?php echo esc_attr($widget_id); ?>">
            <div class="ai-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <?php _e('Getting AI suggestions...', 'spiralengine'); ?>
            </div>
            <div class="ai-suggestions-content"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize AI suggestions for this widget
            spiralEngineAI.initWidgetSuggestions('<?php echo esc_js($widget_id); ?>', <?php echo json_encode($context); ?>);
        });
        </script>
        <?php
    }
    
    /**
     * Get trigger prediction content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_trigger_prediction_content($widget_id, $data) {
        $user_id = get_current_user_id();
        
        // Get recent trigger patterns
        $patterns = $this->pattern_analyzer->analyze_user_patterns($user_id, array(
            'days' => 7,
            'pattern_types' => array('trigger'),
            'widgets' => array('trigger-tracker'),
            'include_ai' => false
        ));
        
        if (isset($patterns['error']) || empty($patterns['patterns']['trigger'])) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="ai-feature trigger-prediction">
            <h4><?php _e('AI Trigger Insights', 'spiralengine'); ?></h4>
            <div class="prediction-content">
                <?php
                $trigger_data = $patterns['patterns']['trigger'];
                
                // Show common triggers
                if (!empty($trigger_data['common_triggers'])) {
                    echo '<div class="common-triggers">';
                    echo '<p>' . __('Your most common triggers:', 'spiralengine') . '</p>';
                    echo '<ul>';
                    foreach (array_slice($trigger_data['common_triggers'], 0, 3, true) as $trigger => $info) {
                        echo '<li>' . esc_html($trigger) . ' (' . $info['percentage'] . '%)</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
                
                // Show trigger timing
                if (!empty($trigger_data['trigger_timing'])) {
                    echo '<div class="trigger-timing">';
                    echo '<p>' . __('Triggers often occur during:', 'spiralengine') . '</p>';
                    // Display timing information
                    echo '</div>';
                }
                ?>
                
                <button class="button ai-analyze-trigger" data-widget="<?php echo esc_attr($widget_id); ?>">
                    <?php _e('Get Detailed Analysis', 'spiralengine'); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get pattern detection content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_pattern_detection_content($widget_id, $data) {
        $user_id = get_current_user_id();
        
        // Check for recent patterns
        $cached_patterns = get_transient('spiralengine_patterns_' . $user_id);
        
        if (!$cached_patterns) {
            return '<div class="ai-feature pattern-detection">
                <p>' . __('Pattern analysis will be available after more data is collected.', 'spiralengine') . '</p>
            </div>';
        }
        
        ob_start();
        ?>
        <div class="ai-feature pattern-detection">
            <h4><?php _e('Detected Patterns', 'spiralengine'); ?></h4>
            <div class="patterns-list">
                <?php
                if (isset($cached_patterns['summary']['key_findings'])) {
                    echo '<ul>';
                    foreach (array_slice($cached_patterns['summary']['key_findings'], 0, 3) as $finding) {
                        echo '<li>' . esc_html($finding) . '</li>';
                    }
                    echo '</ul>';
                }
                ?>
            </div>
            <a href="#" class="view-full-patterns" data-user="<?php echo esc_attr($user_id); ?>">
                <?php _e('View Full Pattern Analysis', 'spiralengine'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get coping suggestions content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_coping_suggestions_content($widget_id, $data) {
        $user_id = get_current_user_id();
        
        // Get AI recommendations for coping
        $recommendations = $this->ai_service->get_recommendations($user_id, 'coping_skills');
        
        if (isset($recommendations['error'])) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="ai-feature coping-suggestions">
            <h4><?php _e('AI-Suggested Coping Strategies', 'spiralengine'); ?></h4>
            <div class="suggestions-content">
                <?php
                if (isset($recommendations['data']['immediate_actions'])) {
                    echo '<div class="immediate-actions">';
                    echo '<p><strong>' . __('Try these now:', 'spiralengine') . '</strong></p>';
                    foreach ($recommendations['data']['immediate_actions'] as $action) {
                        echo '<div class="action-item">';
                        echo '<h5>' . esc_html($action['action']) . '</h5>';
                        echo '<p>' . esc_html($action['rationale']) . '</p>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get mood prediction content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_mood_prediction_content($widget_id, $data) {
        $user_id = get_current_user_id();
        
        // Get recent mood data
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $recent_moods = $wpdb->get_results($wpdb->prepare(
            "SELECT data, created_at FROM $table_name 
            WHERE user_id = %d AND widget_id = 'mood-tracker'
            ORDER BY created_at DESC LIMIT 10",
            $user_id
        ), ARRAY_A);
        
        if (count($recent_moods) < 5) {
            return '<div class="ai-feature mood-prediction">
                <p>' . __('Mood predictions will be available after more entries.', 'spiralengine') . '</p>
            </div>';
        }
        
        ob_start();
        ?>
        <div class="ai-feature mood-prediction">
            <h4><?php _e('Mood Patterns & Predictions', 'spiralengine'); ?></h4>
            <div class="mood-chart" id="mood-prediction-chart"></div>
            <div class="mood-insights">
                <p class="insight-text"></p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                spiralEngineAI.loadMoodPrediction(<?php echo json_encode($recent_moods); ?>);
            });
            </script>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get effectiveness prediction content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_effectiveness_prediction_content($widget_id, $data) {
        ob_start();
        ?>
        <div class="ai-feature effectiveness-prediction">
            <h4><?php _e('Skill Effectiveness Predictor', 'spiralengine'); ?></h4>
            <div class="prediction-interface">
                <label><?php _e('Select a coping skill:', 'spiralengine'); ?></label>
                <select id="skill-selector" class="ai-skill-selector">
                    <option value=""><?php _e('Choose a skill...', 'spiralengine'); ?></option>
                    <?php
                    $skills = $this->get_available_coping_skills();
                    foreach ($skills as $category => $category_skills) {
                        echo '<optgroup label="' . esc_attr($category) . '">';
                        foreach ($category_skills as $skill) {
                            echo '<option value="' . esc_attr($skill['id']) . '">' . esc_html($skill['name']) . '</option>';
                        }
                        echo '</optgroup>';
                    }
                    ?>
                </select>
                <div class="prediction-result" style="display: none;">
                    <div class="effectiveness-score">
                        <span class="score-label"><?php _e('Predicted Effectiveness:', 'spiralengine'); ?></span>
                        <span class="score-value"></span>
                    </div>
                    <p class="prediction-rationale"></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get skill recommendations content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_skill_recommendations_content($widget_id, $data) {
        $user_id = get_current_user_id();
        
        // Get personalized skill recommendations
        $recommendations = $this->generate_skill_recommendations($user_id);
        
        ob_start();
        ?>
        <div class="ai-feature skill-recommendations">
            <h4><?php _e('Recommended Skills for You', 'spiralengine'); ?></h4>
            <div class="skills-grid">
                <?php foreach ($recommendations as $skill): ?>
                <div class="skill-card" data-skill="<?php echo esc_attr($skill['id']); ?>">
                    <h5><?php echo esc_html($skill['name']); ?></h5>
                    <p><?php echo esc_html($skill['description']); ?></p>
                    <div class="skill-meta">
                        <span class="match-score"><?php echo $skill['match_score']; ?>% match</span>
                        <button class="try-skill button-small">
                            <?php _e('Try This', 'spiralengine'); ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get cognitive analysis content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_cognitive_analysis_content($widget_id, $data) {
        ob_start();
        ?>
        <div class="ai-feature cognitive-analysis">
            <h4><?php _e('AI Thought Analysis', 'spiralengine'); ?></h4>
            <div class="analysis-prompt">
                <p><?php _e('The AI can help identify thinking patterns and suggest reframes.', 'spiralengine'); ?></p>
                <button class="button analyze-thought" disabled>
                    <?php _e('Analyze My Thought', 'spiralengine'); ?>
                </button>
                <p class="analysis-hint"><?php _e('Enter a thought above to enable analysis', 'spiralengine'); ?></p>
            </div>
            <div class="analysis-results" style="display: none;">
                <div class="thought-patterns"></div>
                <div class="reframe-suggestions"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get reframe suggestions content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_reframe_suggestions_content($widget_id, $data) {
        ob_start();
        ?>
        <div class="ai-feature reframe-suggestions">
            <div class="suggestions-container">
                <h5><?php _e('AI-Generated Reframes', 'spiralengine'); ?></h5>
                <div class="reframes-list"></div>
                <button class="button get-more-reframes" style="display: none;">
                    <?php _e('Get More Suggestions', 'spiralengine'); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get sentiment analysis content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_sentiment_analysis_content($widget_id, $data) {
        ob_start();
        ?>
        <div class="ai-feature sentiment-analysis">
            <h4><?php _e('Journal Sentiment Analysis', 'spiralengine'); ?></h4>
            <div class="sentiment-meter">
                <div class="meter-container">
                    <div class="meter-fill" data-sentiment="neutral" style="width: 50%"></div>
                </div>
                <div class="sentiment-labels">
                    <span class="negative"><?php _e('Negative', 'spiralengine'); ?></span>
                    <span class="neutral"><?php _e('Neutral', 'spiralengine'); ?></span>
                    <span class="positive"><?php _e('Positive', 'spiralengine'); ?></span>
                </div>
            </div>
            <div class="sentiment-details" style="display: none;">
                <p class="dominant-emotion"></p>
                <div class="emotion-breakdown"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get theme extraction content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_theme_extraction_content($widget_id, $data) {
        ob_start();
        ?>
        <div class="ai-feature theme-extraction">
            <h4><?php _e('Journal Themes', 'spiralengine'); ?></h4>
            <div class="themes-container">
                <div class="extracting-themes" style="display: none;">
                    <span class="spinner is-active"></span>
                    <?php _e('Analyzing themes...', 'spiralengine'); ?>
                </div>
                <div class="themes-list"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get prompt suggestions content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_prompt_suggestions_content($widget_id, $data) {
        $prompts = $this->get_journal_prompts(get_current_user_id());
        
        ob_start();
        ?>
        <div class="ai-feature prompt-suggestions">
            <h4><?php _e('Suggested Prompts', 'spiralengine'); ?></h4>
            <div class="prompts-carousel">
                <?php foreach ($prompts as $prompt): ?>
                <div class="prompt-card">
                    <p><?php echo esc_html($prompt['text']); ?></p>
                    <button class="use-prompt button-small" data-prompt="<?php echo esc_attr($prompt['text']); ?>">
                        <?php _e('Use This', 'spiralengine'); ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="refresh-prompts">
                <?php _e('Get New Prompts', 'spiralengine'); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get goal recommendations content
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @return string HTML content
     */
    private function get_goal_recommendations_content($widget_id, $data) {
        $user_id = get_current_user_id();
        $recommendations = $this->generate_goal_recommendations($user_id);
        
        ob_start();
        ?>
        <div class="ai-feature goal-recommendations">
            <h4><?php _e('AI-Suggested Goals', 'spiralengine'); ?></h4>
            <div class="recommendations-list">
                <?php foreach ($recommendations as $rec): ?>
                <div class="goal-recommendation">
                    <h5><?php echo esc_html($rec['title']); ?></h5>
                    <p><?php echo esc_html($rec['description']); ?></p>
                    <div class="recommendation-meta">
                        <span class="difficulty"><?php echo esc_html($rec['difficulty']); ?></span>
                        <span class="timeframe"><?php echo esc_html($rec['timeframe']); ?></span>
                    </div>
                    <button class="adopt-goal button-small" data-goal='<?php echo json_encode($rec); ?>'>
                        <?php _e('Set This Goal', 'spiralengine'); ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for getting AI suggestions
     *
     * @return void
     */
    public function ajax_get_suggestion() {
        check_ajax_referer('spiralengine_ai_nonce', 'nonce');
        
        $widget_id = isset($_POST['widget_id']) ? sanitize_text_field($_POST['widget_id']) : '';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();
        
        if (empty($widget_id)) {
            wp_send_json_error(__('Invalid widget ID', 'spiralengine'));
        }
        
        $user_id = get_current_user_id();
        $membership = new SpiralEngine_Membership($user_id);
        
        // Check permissions
        if (!isset($this->widget_enhancements[$widget_id]) || 
            !$this->user_can_access_ai_features($membership->get_tier(), $this->widget_enhancements[$widget_id]['min_tier'])) {
            wp_send_json_error(__('AI features not available for your membership tier', 'spiralengine'));
        }
        
        // Generate suggestions based on widget type
        $suggestions = $this->generate_widget_suggestions($widget_id, $context);
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * AJAX handler for analyzing input
     *
     * @return void
     */
    public function ajax_analyze_input() {
        check_ajax_referer('spiralengine_ai_nonce', 'nonce');
        
        $widget_id = isset($_POST['widget_id']) ? sanitize_text_field($_POST['widget_id']) : '';
        $input = isset($_POST['input']) ? sanitize_textarea_field($_POST['input']) : '';
        $analysis_type = isset($_POST['analysis_type']) ? sanitize_text_field($_POST['analysis_type']) : '';
        
        if (empty($widget_id) || empty($input)) {
            wp_send_json_error(__('Invalid input', 'spiralengine'));
        }
        
        $user_id = get_current_user_id();
        
        // Perform analysis
        $analysis = $this->analyze_widget_input($widget_id, $input, $analysis_type);
        
        wp_send_json_success($analysis);
    }
    
    /**
     * AJAX handler for predicting outcomes
     *
     * @return void
     */
    public function ajax_predict_outcome() {
        check_ajax_referer('spiralengine_ai_nonce', 'nonce');
        
        $widget_id = isset($_POST['widget_id']) ? sanitize_text_field($_POST['widget_id']) : '';
        $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : array();
        
        if (empty($widget_id)) {
            wp_send_json_error(__('Invalid widget ID', 'spiralengine'));
        }
        
        $user_id = get_current_user_id();
        
        // Generate prediction
        $prediction = $this->predict_widget_outcome($widget_id, $data, $user_id);
        
        wp_send_json_success($prediction);
    }
    
    /**
     * Generate widget-specific suggestions
     *
     * @param string $widget_id Widget ID
     * @param array $context Context data
     * @return array Suggestions
     */
    private function generate_widget_suggestions($widget_id, $context) {
        switch ($widget_id) {
            case 'trigger-tracker':
                return $this->generate_trigger_suggestions($context);
                
            case 'mood-tracker':
                return $this->generate_mood_suggestions($context);
                
            case 'coping-logger':
                return $this->generate_coping_suggestions($context);
                
            case 'thought-challenger':
                return $this->generate_thought_suggestions($context);
                
            case 'journal-entry':
                return $this->generate_journal_suggestions($context);
                
            case 'goal-setting':
                return $this->generate_goal_suggestions($context);
                
            default:
                return array('suggestions' => array());
        }
    }
    
    /**
     * Generate trigger suggestions
     *
     * @param array $context Context
     * @return array Suggestions
     */
    private function generate_trigger_suggestions($context) {
        $user_id = get_current_user_id();
        
        // Get user's common triggers
        $common_triggers = $this->get_user_common_triggers($user_id);
        
        // Get contextual triggers (time-based, situation-based)
        $contextual_triggers = $this->get_contextual_triggers($context);
        
        return array(
            'common_triggers' => $common_triggers,
            'contextual_triggers' => $contextual_triggers,
            'quick_select' => array_merge(
                array_slice($common_triggers, 0, 3),
                array_slice($contextual_triggers, 0, 2)
            )
        );
    }
    
    /**
     * Analyze widget input
     *
     * @param string $widget_id Widget ID
     * @param string $input User input
     * @param string $analysis_type Type of analysis
     * @return array Analysis results
     */
    private function analyze_widget_input($widget_id, $input, $analysis_type) {
        $user_id = get_current_user_id();
        
        // Prepare content for AI analysis
        $content = array(
            'widget_type' => $widget_id,
            'user_input' => $input,
            'analysis_type' => $analysis_type,
            'timestamp' => current_time('mysql')
        );
        
        // Perform AI analysis
        $params = array(
            'type' => 'input_analysis',
            'widget' => $widget_id,
            'analysis_focus' => $analysis_type
        );
        
        $ai_analysis = $this->ai_service->analyze_episode($content, $params);
        
        if (isset($ai_analysis['error'])) {
            return array(
                'success' => false,
                'message' => $ai_analysis['error']
            );
        }
        
        // Format results based on widget type
        return $this->format_analysis_results($widget_id, $ai_analysis, $analysis_type);
    }
    
    /**
     * Format analysis results for display
     *
     * @param string $widget_id Widget ID
     * @param array $ai_analysis AI analysis results
     * @param string $analysis_type Analysis type
     * @return array Formatted results
     */
    private function format_analysis_results($widget_id, $ai_analysis, $analysis_type) {
        $formatted = array(
            'success' => true,
            'widget' => $widget_id,
            'type' => $analysis_type
        );
        
        switch ($widget_id) {
            case 'thought-challenger':
                if ($analysis_type === 'cognitive_distortions') {
                    $formatted['distortions'] = $ai_analysis['data']['patterns'] ?? array();
                    $formatted['reframes'] = $ai_analysis['data']['coping_suggestions'] ?? array();
                }
                break;
                
            case 'journal-entry':
                if ($analysis_type === 'sentiment') {
                    $formatted['sentiment'] = $ai_analysis['data']['sentiment'] ?? 'neutral';
                    $formatted['emotions'] = $ai_analysis['data']['emotions'] ?? array();
                    $formatted['themes'] = $ai_analysis['data']['themes'] ?? array();
                }
                break;
                
            default:
                $formatted['analysis'] = $ai_analysis['data'] ?? array();
        }
        
        return $formatted;
    }
    
    /**
     * Predict widget outcome
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     * @param int $user_id User ID
     * @return array Prediction
     */
    private function predict_widget_outcome($widget_id, $data, $user_id) {
        // Get historical data for prediction
        $history = $this->get_widget_history($widget_id, $user_id, 30);
        
        // Prepare prediction request
        $prediction_data = array(
            'widget' => $widget_id,
            'current_data' => $data,
            'historical_data' => $history,
            'user_profile' => $this->get_user_profile_for_prediction($user_id)
        );
        
        // Get AI prediction
        $params = array(
            'type' => 'outcome_prediction',
            'widget' => $widget_id
        );
        
        $prediction = $this->ai_service->analyze_episode($prediction_data, $params);
        
        if (isset($prediction['error'])) {
            return array(
                'success' => false,
                'message' => $prediction['error']
            );
        }
        
        return array(
            'success' => true,
            'prediction' => $prediction['data'],
            'confidence' => $prediction['data']['confidence'] ?? 'medium'
        );
    }
    
    /**
     * Get user's common triggers
     *
     * @param int $user_id User ID
     * @return array Common triggers
     */
    private function get_user_common_triggers($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $triggers = $wpdb->get_results($wpdb->prepare(
            "SELECT JSON_EXTRACT(data, '$.trigger_category') as trigger_cat,
                    COUNT(*) as count
            FROM $table_name
            WHERE user_id = %d 
            AND widget_id = 'trigger-tracker'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY trigger_cat
            ORDER BY count DESC
            LIMIT 5",
            $user_id
        ), ARRAY_A);
        
        $common = array();
        foreach ($triggers as $trigger) {
            if (!empty($trigger['trigger_cat'])) {
                $common[] = trim($trigger['trigger_cat'], '"');
            }
        }
        
        return $common;
    }
    
    /**
     * Get contextual triggers
     *
     * @param array $context Context data
     * @return array Contextual triggers
     */
    private function get_contextual_triggers($context) {
        $triggers = array();
        
        // Time-based triggers
        $hour = date('G');
        if ($hour >= 6 && $hour <= 9) {
            $triggers[] = 'Morning routine';
            $triggers[] = 'Work anxiety';
        } elseif ($hour >= 21) {
            $triggers[] = 'Sleep worries';
            $triggers[] = 'End of day stress';
        }
        
        // Day-based triggers
        $day = date('l');
        if ($day === 'Monday') {
            $triggers[] = 'Start of week stress';
        } elseif ($day === 'Sunday') {
            $triggers[] = 'Weekend ending anxiety';
        }
        
        // Weather-based (if available in context)
        if (isset($context['weather'])) {
            if ($context['weather'] === 'rainy') {
                $triggers[] = 'Weather-related mood';
            }
        }
        
        return array_unique($triggers);
    }
    
    /**
     * Get available coping skills
     *
     * @return array Coping skills organized by category
     */
    private function get_available_coping_skills() {
        return array(
            __('Grounding', 'spiralengine') => array(
                array('id' => 'breathing', 'name' => __('Deep Breathing', 'spiralengine')),
                array('id' => '54321', 'name' => __('5-4-3-2-1 Technique', 'spiralengine')),
                array('id' => 'body_scan', 'name' => __('Body Scan', 'spiralengine'))
            ),
            __('Movement', 'spiralengine') => array(
                array('id' => 'walk', 'name' => __('Take a Walk', 'spiralengine')),
                array('id' => 'stretch', 'name' => __('Stretching', 'spiralengine')),
                array('id' => 'exercise', 'name' => __('Exercise', 'spiralengine'))
            ),
            __('Creative', 'spiralengine') => array(
                array('id' => 'journal', 'name' => __('Journaling', 'spiralengine')),
                array('id' => 'draw', 'name' => __('Drawing/Art', 'spiralengine')),
                array('id' => 'music', 'name' => __('Listen to Music', 'spiralengine'))
            ),
            __('Social', 'spiralengine') => array(
                array('id' => 'call_friend', 'name' => __('Call a Friend', 'spiralengine')),
                array('id' => 'support_group', 'name' => __('Support Group', 'spiralengine')),
                array('id' => 'hug', 'name' => __('Hug Someone', 'spiralengine'))
            )
        );
    }
    
    /**
     * Generate personalized skill recommendations
     *
     * @param int $user_id User ID
     * @return array Skill recommendations
     */
    private function generate_skill_recommendations($user_id) {
        // Get user's usage history
        $history = $this->get_coping_skill_history($user_id);
        
        // Get AI recommendations
        $ai_recommendations = $this->ai_service->get_recommendations($user_id, 'coping_skills');
        
        $recommendations = array();
        
        // Process AI recommendations
        if (!isset($ai_recommendations['error']) && isset($ai_recommendations['data']['coping_strategies'])) {
            foreach ($ai_recommendations['data']['coping_strategies'] as $strategy) {
                $recommendations[] = array(
                    'id' => sanitize_title($strategy['strategy']),
                    'name' => $strategy['strategy'],
                    'description' => $strategy['expected_benefit'],
                    'match_score' => rand(75, 95) // In production, this would be calculated
                );
            }
        }
        
        // Add fallback recommendations if needed
        if (empty($recommendations)) {
            $all_skills = $this->get_available_coping_skills();
            foreach ($all_skills as $category => $skills) {
                if (count($recommendations) < 3) {
                    $skill = $skills[array_rand($skills)];
                    $recommendations[] = array(
                        'id' => $skill['id'],
                        'name' => $skill['name'],
                        'description' => sprintf(__('A %s technique that may help', 'spiralengine'), strtolower($category)),
                        'match_score' => rand(60, 80)
                    );
                }
            }
        }
        
        return array_slice($recommendations, 0, 3);
    }
    
    /**
     * Get coping skill history
     *
     * @param int $user_id User ID
     * @return array Skill history
     */
    private function get_coping_skill_history($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT data, created_at FROM $table_name
            WHERE user_id = %d 
            AND widget_id = 'coping-logger'
            ORDER BY created_at DESC
            LIMIT 50",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Get journal prompts
     *
     * @param int $user_id User ID
     * @return array Journal prompts
     */
    private function get_journal_prompts($user_id) {
        // Get AI-generated prompts based on user's recent data
        $recent_insights = $this->insight_generator->get_user_insights($user_id, 'daily', 1);
        
        $prompts = array();
        
        // Context-aware prompts
        $hour = date('G');
        if ($hour < 12) {
            $prompts[] = array(
                'text' => __('What are your intentions for today?', 'spiralengine'),
                'type' => 'morning'
            );
        } elseif ($hour >= 20) {
            $prompts[] = array(
                'text' => __('What went well today?', 'spiralengine'),
                'type' => 'evening'
            );
        }
        
        // Add personalized prompts based on recent patterns
        if (!empty($recent_insights) && isset($recent_insights[0]['data']['ai_insights'])) {
            // Extract themes from insights to generate relevant prompts
            $prompts[] = array(
                'text' => __('Reflect on a recent challenge and how you overcame it.', 'spiralengine'),
                'type' => 'reflection'
            );
        }
        
        // Default prompts
        $default_prompts = array(
            array('text' => __('What emotions am I feeling right now?', 'spiralengine'), 'type' => 'emotion'),
            array('text' => __('What am I grateful for today?', 'spiralengine'), 'type' => 'gratitude'),
            array('text' => __('What would I like to let go of?', 'spiralengine'), 'type' => 'release'),
            array('text' => __('How can I show myself compassion today?', 'spiralengine'), 'type' => 'self-care')
        );
        
        // Mix personalized and default prompts
        $prompts = array_merge($prompts, array_slice($default_prompts, 0, 3 - count($prompts)));
        
        return $prompts;
    }
    
    /**
     * Generate goal recommendations
     *
     * @param int $user_id User ID
     * @return array Goal recommendations
     */
    private function generate_goal_recommendations($user_id) {
        // Get user's patterns and progress
        $patterns = $this->pattern_analyzer->get_cached_results($user_id);
        $current_goals = $this->get_user_current_goals($user_id);
        
        // Get AI recommendations
        $ai_recommendations = $this->ai_service->get_recommendations($user_id, 'goals');
        
        $recommendations = array();
        
        // Process AI recommendations
        if (!isset($ai_recommendations['error']) && isset($ai_recommendations['data']['immediate_actions'])) {
            foreach ($ai_recommendations['data']['immediate_actions'] as $action) {
                $recommendations[] = array(
                    'title' => $action['action'],
                    'description' => $action['rationale'],
                    'difficulty' => $this->assess_goal_difficulty($action),
                    'timeframe' => __('1-2 weeks', 'spiralengine'),
                    'category' => 'wellness'
                );
            }
        }
        
        // Add pattern-based recommendations
        if ($patterns && isset($patterns['summary']['recommendations'])) {
            foreach ($patterns['summary']['recommendations'] as $rec) {
                if ($rec['type'] === 'goal' || $rec['type'] === 'improvement') {
                    $recommendations[] = array(
                        'title' => $this->create_goal_from_recommendation($rec),
                        'description' => $rec['message'],
                        'difficulty' => __('Moderate', 'spiralengine'),
                        'timeframe' => __('2-4 weeks', 'spiralengine'),
                        'category' => 'improvement'
                    );
                }
            }
        }
        
        // Ensure we have at least 3 recommendations
        while (count($recommendations) < 3) {
            $recommendations[] = $this->generate_fallback_goal();
        }
        
        return array_slice($recommendations, 0, 3);
    }
    
    /**
     * Get user's current goals
     *
     * @param int $user_id User ID
     * @return array Current goals
     */
    private function get_user_current_goals($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT data FROM $table_name
            WHERE user_id = %d 
            AND widget_id = 'goal-setting'
            AND JSON_EXTRACT(data, '$.status') = 'active'
            ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Check if user can access AI features
     *
     * @param string $user_tier User's tier
     * @param string $required_tier Required tier
     * @return bool Can access
     */
    private function user_can_access_ai_features($user_tier, $required_tier) {
        $tier_hierarchy = array(
            'free' => 0,
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4
        );
        
        $user_level = isset($tier_hierarchy[$user_tier]) ? $tier_hierarchy[$user_tier] : 0;
        $required_level = isset($tier_hierarchy[$required_tier]) ? $tier_hierarchy[$required_tier] : 0;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Get widget history
     *
     * @param string $widget_id Widget ID
     * @param int $user_id User ID
     * @param int $days Number of days
     * @return array Widget history
     */
    private function get_widget_history($widget_id, $user_id, $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT data, severity, created_at FROM $table_name
            WHERE user_id = %d 
            AND widget_id = %s
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC",
            $user_id,
            $widget_id,
            $days
        ), ARRAY_A);
        
        // Decode JSON data
        foreach ($results as &$result) {
            $result['data'] = json_decode($result['data'], true);
        }
        
        return $results;
    }
    
    /**
     * Get user profile for prediction
     *
     * @param int $user_id User ID
     * @return array User profile
     */
    private function get_user_profile_for_prediction($user_id) {
        $membership = new SpiralEngine_Membership($user_id);
        
        return array(
            'tier' => $membership->get_tier(),
            'member_since' => get_user_by('id', $user_id)->user_registered,
            'total_episodes' => $this->get_total_episodes($user_id),
            'active_days' => $this->get_active_days($user_id)
        );
    }
    
    /**
     * Get total episodes for user
     *
     * @param int $user_id User ID
     * @return int Total episodes
     */
    private function get_total_episodes($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get active days for user
     *
     * @param int $user_id User ID
     * @return int Active days
     */
    private function get_active_days($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT DATE(created_at)) FROM $table_name WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Assess goal difficulty
     *
     * @param array $action Action data
     * @return string Difficulty level
     */
    private function assess_goal_difficulty($action) {
        // Simple heuristic based on action complexity
        $action_text = strtolower($action['action']);
        
        if (strpos($action_text, 'daily') !== false || strpos($action_text, 'simple') !== false) {
            return __('Easy', 'spiralengine');
        } elseif (strpos($action_text, 'weekly') !== false || strpos($action_text, 'regular') !== false) {
            return __('Moderate', 'spiralengine');
        } else {
            return __('Challenging', 'spiralengine');
        }
    }
    
    /**
     * Create goal from recommendation
     *
     * @param array $rec Recommendation
     * @return string Goal title
     */
    private function create_goal_from_recommendation($rec) {
        // Convert recommendation to actionable goal
        $message = $rec['message'];
        
        // Simple transformation rules
        $transformations = array(
            'Consider' => 'Start',
            'Try' => 'Practice',
            'episodes frequently occur' => 'Reduce late night episodes'
        );
        
        foreach ($transformations as $from => $to) {
            $message = str_replace($from, $to, $message);
        }
        
        // Truncate if too long
        if (strlen($message) > 50) {
            $message = substr($message, 0, 47) . '...';
        }
        
        return $message;
    }
    
    /**
     * Generate fallback goal
     *
     * @return array Goal recommendation
     */
    private function generate_fallback_goal() {
        $fallback_goals = array(
            array(
                'title' => __('Practice daily mindfulness', 'spiralengine'),
                'description' => __('Spend 5 minutes each day on mindfulness meditation', 'spiralengine'),
                'difficulty' => __('Easy', 'spiralengine'),
                'timeframe' => __('2 weeks', 'spiralengine'),
                'category' => 'mindfulness'
            ),
            array(
                'title' => __('Improve sleep routine', 'spiralengine'),
                'description' => __('Establish a consistent bedtime routine', 'spiralengine'),
                'difficulty' => __('Moderate', 'spiralengine'),
                'timeframe' => __('3 weeks', 'spiralengine'),
                'category' => 'sleep'
            ),
            array(
                'title' => __('Build support network', 'spiralengine'),
                'description' => __('Connect with one supportive person each week', 'spiralengine'),
                'difficulty' => __('Moderate', 'spiralengine'),
                'timeframe' => __('4 weeks', 'spiralengine'),
                'category' => 'social'
            )
        );
        
        return $fallback_goals[array_rand($fallback_goals)];
    }
}

// Initialize AI widget enhancements
add_action('init', function() {
    if (get_option('spiralengine_ai_enabled', true)) {
        new SpiralEngine_AI_Widget_Enhancements();
    }
});

// Scheduled AI episode analysis
add_action('spiralengine_analyze_episode_ai', function($episode_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'spiralengine_episodes';
    
    // Get episode data
    $episode = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $episode_id
    ), ARRAY_A);
    
    if (!$episode) {
        return;
    }
    
    // Decode JSON data
    $episode['data'] = json_decode($episode['data'], true);
    $episode['metadata'] = json_decode($episode['metadata'], true);
    
    // Perform AI analysis
    $ai_service = SpiralEngine_AI_Service::get_instance();
    $analysis = $ai_service->analyze_episode($episode);
    
    if (!isset($analysis['error'])) {
        // Store analysis results in metadata
        $metadata = $episode['metadata'] ?? array();
        $metadata['ai_analysis'] = $analysis;
        
        // Update episode with analysis
        $wpdb->update(
            $table_name,
            array('metadata' => json_encode($metadata)),
            array('id' => $episode_id),
            array('%s'),
            array('%d')
        );
        
        // Trigger any post-analysis actions
        do_action('spiralengine_episode_analyzed', $episode_id, $analysis);
    }
});


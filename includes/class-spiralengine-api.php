<?php
/**
 * SpiralEngine Internal API
 *
 * Provides internal API endpoints for widgets and frontend interactions
 *
 * @package SpiralEngine
 * @since 1.0.0
 */

// includes/class-spiralengine-api.php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Internal API class
 */
class SpiralEngine_API {
    
    /**
     * Core instance
     *
     * @var SpiralEngine_Core
     */
    private $core;
    
    /**
     * Security instance
     *
     * @var SpiralEngine_Security
     */
    private $security;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->core = SpiralEngine_Core::get_instance();
        
        // Get security instance when available
        add_action('spiralengine_components_loaded', function($core) {
            $this->security = $core->security;
        });
    }
    
    /**
     * Get episodes (REST endpoint)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function get_episodes($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['error' => 'Not authenticated'], 401);
        }
        
        $widget_id = $request->get_param('widget_id') ?: 'all';
        $args = [
            'limit' => $request->get_param('limit') ?: 50,
            'offset' => $request->get_param('offset') ?: 0,
            'order' => $request->get_param('order') ?: 'DESC',
            'orderby' => $request->get_param('orderby') ?: 'created_at',
            'date_from' => $request->get_param('date_from') ?: '',
            'date_to' => $request->get_param('date_to') ?: '',
            'severity_min' => $request->get_param('severity_min') ?: 0,
            'severity_max' => $request->get_param('severity_max') ?: 10
        ];
        
        $episodes = $this->core->get_episodes($widget_id, $user_id, $args);
        
        return new WP_REST_Response([
            'episodes' => $episodes,
            'total' => count($episodes),
            'args' => $args
        ]);
    }
    
    /**
     * Create episode (REST endpoint)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function create_episode($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['error' => 'Not authenticated'], 401);
        }
        
        $widget_id = $request->get_param('widget_id');
        $data = $request->get_param('data');
        $metadata = $request->get_param('metadata') ?: [];
        
        if (!$widget_id || !$data) {
            return new WP_REST_Response(['error' => 'Missing required parameters'], 400);
        }
        
        $episode_id = $this->core->save_episode($widget_id, $data, $metadata);
        
        if (!$episode_id) {
            return new WP_REST_Response(['error' => 'Failed to save episode'], 500);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'episode_id' => $episode_id
        ]);
    }
    
    /**
     * Get insights (REST endpoint)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function get_insights($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['error' => 'Not authenticated'], 401);
        }
        
        // Check permission
        if (!$this->core->can('view_insights', $user_id)) {
            return new WP_REST_Response(['error' => 'Permission denied'], 403);
        }
        
        $widget_id = $request->get_param('widget_id') ?: 'all';
        $period = $request->get_param('period') ?: '30days';
        
        $insights = $this->generate_insights($user_id, $widget_id, $period);
        
        return new WP_REST_Response($insights);
    }
    
    /**
     * Get users (Admin REST endpoint)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function get_users($request) {
        // Permission check handled by REST permission callback
        
        global $wpdb;
        
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 50;
        $search = $request->get_param('search') ?: '';
        $tier = $request->get_param('tier') ?: '';
        $status = $request->get_param('status') ?: '';
        
        $offset = ($page - 1) * $per_page;
        
        // Build query
        $query = "SELECT u.*, m.* 
                  FROM {$wpdb->users} u
                  LEFT JOIN {$wpdb->prefix}spiralengine_memberships m ON u.ID = m.user_id
                  WHERE 1=1";
        
        $query_args = [];
        
        if ($search) {
            $query .= " AND (u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_args[] = $search_term;
            $query_args[] = $search_term;
            $query_args[] = $search_term;
        }
        
        if ($tier) {
            $query .= " AND m.tier = %s";
            $query_args[] = $tier;
        }
        
        if ($status) {
            $query .= " AND m.status = %s";
            $query_args[] = $status;
        }
        
        $query .= " ORDER BY u.ID DESC LIMIT %d OFFSET %d";
        $query_args[] = $per_page;
        $query_args[] = $offset;
        
        $users = $wpdb->get_results(
            $wpdb->prepare($query, $query_args),
            ARRAY_A
        );
        
        // Get total count
        $count_query = "SELECT COUNT(DISTINCT u.ID) 
                        FROM {$wpdb->users} u
                        LEFT JOIN {$wpdb->prefix}spiralengine_memberships m ON u.ID = m.user_id
                        WHERE 1=1";
        
        if ($search || $tier || $status) {
            // Apply same filters for count
            $count_args = array_slice($query_args, 0, -2); // Remove limit and offset
            $count = $wpdb->get_var($wpdb->prepare($count_query, $count_args));
        } else {
            $count = $wpdb->get_var($count_query);
        }
        
        // Format user data
        $formatted_users = [];
        foreach ($users as $user) {
            $formatted_users[] = $this->format_user_data($user);
        }
        
        return new WP_REST_Response([
            'users' => $formatted_users,
            'total' => intval($count),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($count / $per_page)
        ]);
    }
    
    /**
     * Generate insights
     *
     * @param int $user_id User ID
     * @param string $widget_id Widget ID
     * @param string $period Time period
     * @return array Insights data
     */
    private function generate_insights(int $user_id, string $widget_id, string $period): array {
        global $wpdb;
        
        // Calculate date range
        $date_from = $this->calculate_date_from($period);
        
        // Get episodes
        $episodes = $this->core->get_episodes($widget_id, $user_id, [
            'date_from' => $date_from,
            'limit' => 1000
        ]);
        
        // Calculate statistics
        $insights = [
            'period' => $period,
            'date_from' => $date_from,
            'date_to' => current_time('mysql'),
            'total_episodes' => count($episodes),
            'widgets' => []
        ];
        
        // Group by widget
        $widget_data = [];
        foreach ($episodes as $episode) {
            $wid = $episode['widget_id'];
            if (!isset($widget_data[$wid])) {
                $widget_data[$wid] = [
                    'count' => 0,
                    'severities' => [],
                    'total_severity' => 0
                ];
            }
            
            $widget_data[$wid]['count']++;
            $widget_data[$wid]['severities'][] = $episode['severity'];
            $widget_data[$wid]['total_severity'] += $episode['severity'];
        }
        
        // Calculate widget insights
        foreach ($widget_data as $wid => $data) {
            $insights['widgets'][$wid] = [
                'count' => $data['count'],
                'average_severity' => $data['count'] > 0 ? round($data['total_severity'] / $data['count'], 2) : 0,
                'min_severity' => !empty($data['severities']) ? min($data['severities']) : 0,
                'max_severity' => !empty($data['severities']) ? max($data['severities']) : 0,
                'trend' => $this->calculate_trend($user_id, $wid, $period)
            ];
        }
        
        // Overall statistics
        $all_severities = array_column($episodes, 'severity');
        $insights['overall'] = [
            'average_severity' => !empty($all_severities) ? round(array_sum($all_severities) / count($all_severities), 2) : 0,
            'episodes_per_day' => $this->calculate_episodes_per_day($episodes, $period),
            'most_active_time' => $this->calculate_most_active_time($episodes),
            'patterns' => $this->detect_patterns($episodes)
        ];
        
        return $insights;
    }
    
    /**
     * Calculate date from period
     *
     * @param string $period Period string
     * @return string Date string
     */
    private function calculate_date_from(string $period): string {
        switch ($period) {
            case '7days':
                return date('Y-m-d', strtotime('-7 days'));
            case '30days':
                return date('Y-m-d', strtotime('-30 days'));
            case '90days':
                return date('Y-m-d', strtotime('-90 days'));
            case 'year':
                return date('Y-m-d', strtotime('-1 year'));
            default:
                return date('Y-m-d', strtotime('-30 days'));
        }
    }
    
    /**
     * Calculate trend
     *
     * @param int $user_id User ID
     * @param string $widget_id Widget ID
     * @param string $period Period
     * @return string Trend direction
     */
    private function calculate_trend(int $user_id, string $widget_id, string $period): string {
        // Compare current period with previous period
        $current_from = $this->calculate_date_from($period);
        $previous_from = date('Y-m-d', strtotime($current_from . ' -' . $period));
        
        $current_episodes = $this->core->get_episodes($widget_id, $user_id, [
            'date_from' => $current_from
        ]);
        
        $previous_episodes = $this->core->get_episodes($widget_id, $user_id, [
            'date_from' => $previous_from,
            'date_to' => $current_from
        ]);
        
        $current_count = count($current_episodes);
        $previous_count = count($previous_episodes);
        
        if ($current_count > $previous_count * 1.1) {
            return 'increasing';
        } elseif ($current_count < $previous_count * 0.9) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Calculate episodes per day
     *
     * @param array $episodes Episodes
     * @param string $period Period
     * @return float Episodes per day
     */
    private function calculate_episodes_per_day(array $episodes, string $period): float {
        if (empty($episodes)) {
            return 0;
        }
        
        $days = $this->get_period_days($period);
        return round(count($episodes) / $days, 2);
    }
    
    /**
     * Get period days
     *
     * @param string $period Period
     * @return int Number of days
     */
    private function get_period_days(string $period): int {
        switch ($period) {
            case '7days':
                return 7;
            case '30days':
                return 30;
            case '90days':
                return 90;
            case 'year':
                return 365;
            default:
                return 30;
        }
    }
    
    /**
     * Calculate most active time
     *
     * @param array $episodes Episodes
     * @return array Most active time data
     */
    private function calculate_most_active_time(array $episodes): array {
        if (empty($episodes)) {
            return ['hour' => null, 'day' => null];
        }
        
        $hours = [];
        $days = [];
        
        foreach ($episodes as $episode) {
            $timestamp = strtotime($episode['created_at']);
            $hour = date('H', $timestamp);
            $day = date('w', $timestamp);
            
            $hours[$hour] = ($hours[$hour] ?? 0) + 1;
            $days[$day] = ($days[$day] ?? 0) + 1;
        }
        
        arsort($hours);
        arsort($days);
        
        $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        return [
            'hour' => key($hours),
            'day' => $day_names[key($days)] ?? null
        ];
    }
    
    /**
     * Detect patterns
     *
     * @param array $episodes Episodes
     * @return array Detected patterns
     */
    private function detect_patterns(array $episodes): array {
        $patterns = [];
        
        if (empty($episodes)) {
            return $patterns;
        }
        
        // Severity patterns
        $severities = array_column($episodes, 'severity');
        $avg_severity = array_sum($severities) / count($severities);
        
        if ($avg_severity > 7) {
            $patterns[] = [
                'type' => 'high_severity',
                'message' => 'Average severity is high',
                'recommendation' => 'Consider seeking professional support'
            ];
        }
        
        // Frequency patterns
        $dates = array_map(function($ep) {
            return date('Y-m-d', strtotime($ep['created_at']));
        }, $episodes);
        
        $date_counts = array_count_values($dates);
        $max_per_day = max($date_counts);
        
        if ($max_per_day > 5) {
            $patterns[] = [
                'type' => 'high_frequency',
                'message' => 'Multiple episodes logged in a single day',
                'recommendation' => 'Try to identify triggers for these clusters'
            ];
        }
        
        // Time-based patterns
        $morning = $afternoon = $evening = $night = 0;
        foreach ($episodes as $episode) {
            $hour = intval(date('H', strtotime($episode['created_at'])));
            if ($hour >= 6 && $hour < 12) $morning++;
            elseif ($hour >= 12 && $hour < 17) $afternoon++;
            elseif ($hour >= 17 && $hour < 22) $evening++;
            else $night++;
        }
        
        $time_distribution = [
            'morning' => $morning,
            'afternoon' => $afternoon,
            'evening' => $evening,
            'night' => $night
        ];
        
        $max_time = array_keys($time_distribution, max($time_distribution))[0];
        if (max($time_distribution) > count($episodes) * 0.5) {
            $patterns[] = [
                'type' => 'time_concentration',
                'message' => "Most episodes occur during the $max_time",
                'recommendation' => "Focus on coping strategies for $max_time hours"
            ];
        }
        
        return $patterns;
    }
    
    /**
     * Format user data
     *
     * @param array $user Raw user data
     * @return array Formatted user data
     */
    private function format_user_data(array $user): array {
        return [
            'id' => intval($user['ID']),
            'username' => $user['user_login'],
            'email' => $user['user_email'],
            'display_name' => $user['display_name'],
            'registered' => $user['user_registered'],
            'membership' => [
                'tier' => $user['tier'] ?? SPIRALENGINE_TIER_FREE,
                'status' => $user['status'] ?? SPIRALENGINE_STATUS_ACTIVE,
                'starts_at' => $user['starts_at'] ?? null,
                'expires_at' => $user['expires_at'] ?? null,
                'stripe_customer_id' => $user['stripe_customer_id'] ?? null
            ],
            'stats' => $this->get_user_stats(intval($user['ID']))
        ];
    }
    
    /**
     * Get user statistics
     *
     * @param int $user_id User ID
     * @return array User statistics
     */
    private function get_user_stats(int $user_id): array {
        global $wpdb;
        
        $stats = [
            'total_episodes' => 0,
            'episodes_this_month' => 0,
            'last_active' => null
        ];
        
        // Total episodes
        $stats['total_episodes'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes WHERE user_id = %d",
                $user_id
            )
        );
        
        // Episodes this month
        $stats['episodes_this_month'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes 
                WHERE user_id = %d AND created_at >= %s",
                $user_id,
                date('Y-m-01 00:00:00')
            )
        );
        
        // Last active
        $stats['last_active'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(created_at) FROM {$wpdb->prefix}spiralengine_episodes WHERE user_id = %d",
                $user_id
            )
        );
        
        return $stats;
    }
    
    /**
     * Render field helper
     *
     * @param string $type Field type
     * @param array $config Field configuration
     * @return string Rendered field HTML
     */
    public function render_field(string $type, array $config): string {
        $defaults = [
            'id' => '',
            'name' => '',
            'value' => '',
            'label' => '',
            'class' => 'spiralengine-field',
            'required' => false,
            'placeholder' => '',
            'options' => [],
            'min' => '',
            'max' => '',
            'step' => ''
        ];
        
        $config = wp_parse_args($config, $defaults);
        
        ob_start();
        
        switch ($type) {
            case 'text':
            case 'email':
            case 'url':
            case 'tel':
                $this->render_text_field($type, $config);
                break;
                
            case 'number':
                $this->render_number_field($config);
                break;
                
            case 'textarea':
                $this->render_textarea_field($config);
                break;
                
            case 'select':
                $this->render_select_field($config);
                break;
                
            case 'radio':
                $this->render_radio_field($config);
                break;
                
            case 'checkbox':
                $this->render_checkbox_field($config);
                break;
                
            case 'range':
                $this->render_range_field($config);
                break;
                
            case 'date':
            case 'time':
            case 'datetime-local':
                $this->render_datetime_field($type, $config);
                break;
                
            default:
                echo '<!-- Unknown field type: ' . esc_html($type) . ' -->';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render text field
     *
     * @param string $type Field type
     * @param array $config Field configuration
     */
    private function render_text_field(string $type, array $config) {
        ?>
        <div class="spiralengine-field-wrapper">
            <?php if ($config['label']): ?>
                <label for="<?php echo esc_attr($config['id']); ?>" class="spiralengine-label">
                    <?php echo esc_html($config['label']); ?>
                    <?php if ($config['required']): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>
            
            <input type="<?php echo esc_attr($type); ?>"
                   id="<?php echo esc_attr($config['id']); ?>"
                   name="<?php echo esc_attr($config['name']); ?>"
                   value="<?php echo esc_attr($config['value']); ?>"
                   class="<?php echo esc_attr($config['class']); ?>"
                   placeholder="<?php echo esc_attr($config['placeholder']); ?>"
                   <?php echo $config['required'] ? 'required' : ''; ?>>
        </div>
        <?php
    }
    
    /**
     * Render number field
     *
     * @param array $config Field configuration
     */
    private function render_number_field(array $config) {
        ?>
        <div class="spiralengine-field-wrapper">
            <?php if ($config['label']): ?>
                <label for="<?php echo esc_attr($config['id']); ?>" class="spiralengine-label">
                    <?php echo esc_html($config['label']); ?>
                    <?php if ($config['required']): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>
            
            <input type="number"
                   id="<?php echo esc_attr($config['id']); ?>"
                   name="<?php echo esc_attr($config['name']); ?>"
                   value="<?php echo esc_attr($config['value']); ?>"
                   class="<?php echo esc_attr($config['class']); ?>"
                   placeholder="<?php echo esc_attr($config['placeholder']); ?>"
                   <?php echo $config['min'] !== '' ? 'min="' . esc_attr($config['min']) . '"' : ''; ?>
                   <?php echo $config['max'] !== '' ? 'max="' . esc_attr($config['max']) . '"' : ''; ?>
                   <?php echo $config['step'] !== '' ? 'step="' . esc_attr($config['step']) . '"' : ''; ?>
                   <?php echo $config['required'] ? 'required' : ''; ?>>
        </div>
        <?php
    }
    
    /**
     * Render textarea field
     *
     * @param array $config Field configuration
     */
    private function render_textarea_field(array $config) {
        ?>
        <div class="spiralengine-field-wrapper">
            <?php if ($config['label']): ?>
                <label for="<?php echo esc_attr($config['id']); ?>" class="spiralengine-label">
                    <?php echo esc_html($config['label']); ?>
                    <?php if ($config['required']): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>
            
            <textarea id="<?php echo esc_attr($config['id']); ?>"
                      name="<?php echo esc_attr($config['name']); ?>"
                      class="<?php echo esc_attr($config['class']); ?>"
                      placeholder="<?php echo esc_attr($config['placeholder']); ?>"
                      rows="<?php echo esc_attr($config['rows'] ?? 4); ?>"
                      <?php echo $config['required'] ? 'required' : ''; ?>><?php echo esc_textarea($config['value']); ?></textarea>
        </div>
        <?php
    }
    
    /**
     * Render select field
     *
     * @param array $config Field configuration
     */
    private function render_select_field(array $config) {
        ?>
        <div class="spiralengine-field-wrapper">
            <?php if ($config['label']): ?>
                <label for="<?php echo esc_attr($config['id']); ?>" class="spiralengine-label">
                    <?php echo esc_html($config['label']); ?>
                    <?php if ($config['required']): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>
            
            <select id="<?php echo esc_attr($config['id']); ?>"
                    name="<?php echo esc_attr($config['name']); ?>"
                    class="<?php echo esc_attr($config['class']); ?>"
                    <?php echo $config['required'] ? 'required' : ''; ?>>
                
                <?php if ($config['placeholder']): ?>
                    <option value=""><?php echo esc_html($config['placeholder']); ?></option>
                <?php endif; ?>
                
                <?php foreach ($config['options'] as $option_value => $option_label): ?>
                    <option value="<?php echo esc_attr($option_value); ?>"
                            <?php selected($config['value'], $option_value); ?>>
                        <?php echo esc_html($option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }
    
    /**
     * Render radio field
     *
     * @param array $config Field configuration
     */
    private function render_radio_field(array $config) {
        ?>
        <div class="spiralengine-field-wrapper">
            <?php if ($config['label']): ?>
                <div class="spiralengine-label">
                    <?php echo esc_html($config['label']); ?>
                    <?php if ($config['required']): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="spiralengine-radio-group">
                <?php foreach ($config['options'] as $option_value => $option_label): ?>
                    <label class="spiralengine-radio-label">
                        <input type="radio"
                               name="<?php echo esc_attr($config['name']); ?>"
                               value="<?php echo esc_attr($option_value); ?>"
                               class="<?php echo esc_attr($config['class']); ?>"
                               <?php checked($config['value'], $option_value); ?>
                               <?php echo $config['required'] ? 'required' : ''; ?>>
                        <?php echo esc_html($option_label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render checkbox field
     *
     * @param array $config Field configuration
     */
    private function render_checkbox_field(array $config) {
        ?>
        <div class="spiralengine-field-wrapper">
            <label class="spiralengine-checkbox-label">
                <input type="checkbox"
                       id="<?php echo esc_attr($config['id']); ?>"
                       name="<?php echo esc_attr($config['name']); ?>"
                       value="1"
                       class="<?php echo esc_attr($config['class']); ?>"
                       <?php checked($config['value'], '1'); ?>
                       <?php echo $config['required'] ? 'required' : ''; ?>>
                <?php echo esc_html($config['label']); ?>
                <?php if ($config['required']): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
        </div>
        <?php
    }
    
    /**
     * Render range field
     *
     * @param array $config Field configuration
     */
    private function render_range_field(array $config) {
        ?>
        <div class="spiralengine-field-wrapper spiralengine-range-wrapper">
            <?php if ($config['label']): ?>
                <label for="<?php echo esc_attr($config['id']); ?>" class="spiralengine-label">
                    <?php echo esc_html($config['label']); ?>
                    <?php if ($config['required']): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>
            
            <div class="spiralengine-range-container">
                <input type="range"
                       id="<?php echo esc_attr($config['id']); ?>"
                       name="<?php echo esc_attr($config['name']); ?>"
                       value="<?php echo esc_attr($config['value']); ?>"
                       class="<?php echo esc_attr($config['class']); ?>"
                       min="<?php echo esc_attr($config['min'] ?? 0); ?>"
                       max="<?php echo esc_attr($config['max'] ?? 10); ?>"
                       step="<?php echo esc_attr($config['step'] ?? 1); ?>"
                       <?php echo $config['required'] ? 'required' : ''; ?>>
                
                <span class="spiralengine-range-value" id="<?php echo esc_attr($config['id']); ?>-value">
                    <?php echo esc_html($config['value']); ?>
                </span>
            </div>
            
            <script>
                document.getElementById('<?php echo esc_js($config['id']); ?>').addEventListener('input', function(e) {
                    document.getElementById('<?php echo esc_js($config['id']); ?>-value').textContent = e.target.value;
                });
            </script>
        </div>
        <?php
    }
    
    /**
     * Render datetime field
     *
     * @param string $type Field type
     * @param array $config Field configuration
     */
    private function render_datetime_field(string $type, array $config) {
        ?>
        <div class="spiralengine-field-wrapper">
            <?php if ($config['label']): ?>
                <label for="<?php echo esc_attr($config['id']); ?>" class="spiralengine-label">
                    <?php echo esc_html($config['label']); ?>
                    <?php if ($config['required']): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>
            
            <input type="<?php echo esc_attr($type); ?>"
                   id="<?php echo esc_attr($config['id']); ?>"
                   name="<?php echo esc_attr($config['name']); ?>"
                   value="<?php echo esc_attr($config['value']); ?>"
                   class="<?php echo esc_attr($config['class']); ?>"
                   <?php echo $config['min'] !== '' ? 'min="' . esc_attr($config['min']) . '"' : ''; ?>
                   <?php echo $config['max'] !== '' ? 'max="' . esc_attr($config['max']) . '"' : ''; ?>
                   <?php echo $config['required'] ? 'required' : ''; ?>>
        </div>
        <?php
    }
}

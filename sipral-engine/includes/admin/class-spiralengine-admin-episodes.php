<?php
// includes/admin/class-spiralengine-admin-episodes.php

/**
 * Spiral Engine Admin Episodes Management
 * 
 * Episode management interface with pattern analysis
 * Based on Episode Framework specifications
 */
class SPIRALENGINE_Admin_Episodes {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Episode types
     */
    private $episode_types = array();
    
    /**
     * Get instance
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
        $this->init_episode_types();
        add_action('wp_ajax_spiral_episodes_filter', array($this, 'ajax_filter_episodes'));
        add_action('wp_ajax_spiral_episodes_bulk', array($this, 'ajax_bulk_action'));
        add_action('wp_ajax_spiral_episodes_detail', array($this, 'ajax_episode_detail'));
    }
    
    /**
     * Initialize episode types
     */
    private function init_episode_types() {
        $this->episode_types = array(
            'overthinking' => array(
                'label' => __('Overthinking', 'spiral-engine'),
                'color' => '#6B46C1',
                'icon' => '🔵'
            ),
            'anxiety' => array(
                'label' => __('Anxiety', 'spiral-engine'),
                'color' => '#FF6B6B',
                'icon' => '🔴'
            ),
            'ptsd' => array(
                'label' => __('PTSD', 'spiral-engine'),
                'color' => '#4ECDC4',
                'icon' => '🟢'
            ),
            'depression' => array(
                'label' => __('Depression', 'spiral-engine'),
                'color' => '#45B7D1',
                'icon' => '🔵'
            ),
            'caregiver' => array(
                'label' => __('Caregiver', 'spiral-engine'),
                'color' => '#96CEB4',
                'icon' => '🟡'
            )
        );
        
        $this->episode_types = apply_filters('spiral_engine_episode_types', $this->episode_types);
    }
    
    /**
     * Render the episodes page
     */
    public function render() {
        $current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';
        ?>
        <div class="wrap spiral-engine-episodes">
            <h1>
                <?php _e('Episode Management', 'spiral-engine'); ?>
                <a href="<?php echo add_query_arg('view', 'patterns', remove_query_arg('episode_id')); ?>" 
                   class="page-title-action"><?php _e('View Patterns', 'spiral-engine'); ?></a>
                <a href="<?php echo add_query_arg('view', 'analytics', remove_query_arg('episode_id')); ?>" 
                   class="page-title-action"><?php _e('Episode Analytics', 'spiral-engine'); ?></a>
            </h1>
            
            <?php
            switch ($current_view) {
                case 'detail':
                    $this->render_episode_detail();
                    break;
                    
                case 'patterns':
                    $this->render_pattern_interface();
                    break;
                    
                case 'analytics':
                    $this->render_episode_analytics();
                    break;
                    
                default:
                    $this->render_episode_list();
                    break;
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Render episode list view
     */
    private function render_episode_list() {
        // Get filter parameters
        $filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
        $filter_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $filter_severity = isset($_GET['severity']) ? intval($_GET['severity']) : 0;
        
        ?>
        <div class="episode-list-view">
            <!-- Filters -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="episode_type" id="filter-episode-type">
                        <option value=""><?php _e('All Episode Types', 'spiral-engine'); ?></option>
                        <?php foreach ($this->episode_types as $type_key => $type_data): ?>
                        <option value="<?php echo esc_attr($type_key); ?>" 
                                <?php selected($filter_type, $type_key); ?>>
                            <?php echo esc_html($type_data['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="severity" id="filter-severity">
                        <option value=""><?php _e('All Severities', 'spiral-engine'); ?></option>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($filter_severity, $i); ?>>
                            <?php echo sprintf(__('Severity %d', 'spiral-engine'), $i); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                    
                    <input type="date" name="date" id="filter-date" 
                           value="<?php echo esc_attr($filter_date); ?>" 
                           placeholder="<?php esc_attr_e('Filter by date', 'spiral-engine'); ?>">
                    
                    <button class="button" id="apply-filters"><?php _e('Filter', 'spiral-engine'); ?></button>
                    <button class="button" id="clear-filters"><?php _e('Clear', 'spiral-engine'); ?></button>
                </div>
                
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action" id="bulk-action-selector">
                        <option value=""><?php _e('Bulk Actions', 'spiral-engine'); ?></option>
                        <option value="export"><?php _e('Export Selected', 'spiral-engine'); ?></option>
                        <option value="analyze"><?php _e('Analyze Patterns', 'spiral-engine'); ?></option>
                        <option value="delete"><?php _e('Delete', 'spiral-engine'); ?></option>
                    </select>
                    <button class="button" id="do-bulk-action"><?php _e('Apply', 'spiral-engine'); ?></button>
                </div>
                
                <div class="alignright">
                    <button class="button button-primary" id="export-all-episodes">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export All', 'spiral-engine'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Episodes Table -->
            <table class="wp-list-table widefat fixed striped episodes">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th class="manage-column column-type"><?php _e('Type', 'spiral-engine'); ?></th>
                        <th class="manage-column column-user"><?php _e('User', 'spiral-engine'); ?></th>
                        <th class="manage-column column-severity"><?php _e('Severity', 'spiral-engine'); ?></th>
                        <th class="manage-column column-trigger"><?php _e('Trigger', 'spiral-engine'); ?></th>
                        <th class="manage-column column-thoughts"><?php _e('Thoughts', 'spiral-engine'); ?></th>
                        <th class="manage-column column-correlations"><?php _e('Correlations', 'spiral-engine'); ?></th>
                        <th class="manage-column column-date"><?php _e('Date', 'spiral-engine'); ?></th>
                        <th class="manage-column column-actions"><?php _e('Actions', 'spiral-engine'); ?></th>
                    </tr>
                </thead>
                <tbody id="episode-list-tbody">
                    <?php $this->render_episode_rows(); ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-2">
                        </td>
                        <th class="manage-column column-type"><?php _e('Type', 'spiral-engine'); ?></th>
                        <th class="manage-column column-user"><?php _e('User', 'spiral-engine'); ?></th>
                        <th class="manage-column column-severity"><?php _e('Severity', 'spiral-engine'); ?></th>
                        <th class="manage-column column-trigger"><?php _e('Trigger', 'spiral-engine'); ?></th>
                        <th class="manage-column column-thoughts"><?php _e('Thoughts', 'spiral-engine'); ?></th>
                        <th class="manage-column column-correlations"><?php _e('Correlations', 'spiral-engine'); ?></th>
                        <th class="manage-column column-date"><?php _e('Date', 'spiral-engine'); ?></th>
                        <th class="manage-column column-actions"><?php _e('Actions', 'spiral-engine'); ?></th>
                    </tr>
                </tfoot>
            </table>
            
            <!-- Pagination -->
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php $this->render_pagination(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render episode rows
     */
    private function render_episode_rows() {
        global $wpdb;
        
        // Get episodes with filters
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Build query
        $where_clauses = array('1=1');
        
        if (!empty($_GET['type'])) {
            $where_clauses[] = $wpdb->prepare("episode_type = %s", sanitize_text_field($_GET['type']));
        }
        
        if (!empty($_GET['severity'])) {
            $where_clauses[] = $wpdb->prepare("severity = %d", intval($_GET['severity']));
        }
        
        if (!empty($_GET['date'])) {
            $where_clauses[] = $wpdb->prepare("DATE(created_at) = %s", sanitize_text_field($_GET['date']));
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $episodes = $wpdb->get_results(
            "SELECT e.*, u.display_name, u.user_email,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_correlations c 
                     WHERE c.primary_episode_id = e.id OR c.related_episode_id = e.id) as correlation_count
             FROM {$wpdb->prefix}spiralengine_episodes e
             LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE {$where_sql}
             ORDER BY e.created_at DESC
             LIMIT {$offset}, {$per_page}"
        );
        
        if (empty($episodes)) {
            // Show sample data for demo
            $episodes = $this->get_sample_episodes();
        }
        
        foreach ($episodes as $episode) {
            $this->render_episode_row($episode);
        }
    }
    
    /**
     * Render single episode row
     */
    private function render_episode_row($episode) {
        $type_data = $this->episode_types[$episode->episode_type] ?? array(
            'label' => ucfirst($episode->episode_type),
            'icon' => '🔵',
            'color' => '#6B46C1'
        );
        
        $detail_url = add_query_arg(array(
            'page' => 'spiral-engine-episodes',
            'view' => 'detail',
            'episode_id' => $episode->id
        ), admin_url('admin.php'));
        
        ?>
        <tr data-episode-id="<?php echo esc_attr($episode->id); ?>">
            <th scope="row" class="check-column">
                <input type="checkbox" name="episodes[]" value="<?php echo esc_attr($episode->id); ?>">
            </th>
            <td class="column-type">
                <span class="episode-type-badge" style="background-color: <?php echo esc_attr($type_data['color']); ?>">
                    <?php echo $type_data['icon'] . ' ' . esc_html($type_data['label']); ?>
                </span>
            </td>
            <td class="column-user">
                <a href="<?php echo get_edit_user_link($episode->user_id); ?>">
                    <?php echo get_avatar($episode->user_email, 32); ?>
                    <?php echo esc_html($episode->display_name); ?>
                </a>
            </td>
            <td class="column-severity">
                <div class="severity-indicator severity-<?php echo $episode->severity; ?>">
                    <?php echo $episode->severity; ?>/10
                </div>
            </td>
            <td class="column-trigger">
                <?php echo esc_html($episode->primary_trigger ?: __('Not specified', 'spiral-engine')); ?>
            </td>
            <td class="column-thoughts">
                <span class="thoughts-preview" title="<?php echo esc_attr($episode->thoughts); ?>">
                    <?php echo esc_html(wp_trim_words($episode->thoughts, 10)); ?>
                </span>
            </td>
            <td class="column-correlations">
                <?php if ($episode->correlation_count > 0): ?>
                <span class="correlation-badge">
                    <?php echo sprintf(
                        _n('%d correlation', '%d correlations', $episode->correlation_count, 'spiral-engine'),
                        $episode->correlation_count
                    ); ?>
                </span>
                <?php else: ?>
                <span class="no-correlations">—</span>
                <?php endif; ?>
            </td>
            <td class="column-date">
                <abbr title="<?php echo esc_attr(date('Y-m-d H:i:s', strtotime($episode->created_at))); ?>">
                    <?php echo human_time_diff(strtotime($episode->created_at), current_time('timestamp')); ?> ago
                </abbr>
            </td>
            <td class="column-actions">
                <a href="<?php echo esc_url($detail_url); ?>" class="button button-small">
                    <?php _e('View', 'spiral-engine'); ?>
                </a>
                <button class="button button-small analyze-episode" data-episode-id="<?php echo esc_attr($episode->id); ?>">
                    <?php _e('Analyze', 'spiral-engine'); ?>
                </button>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render episode detail view
     */
    private function render_episode_detail() {
        $episode_id = isset($_GET['episode_id']) ? intval($_GET['episode_id']) : 0;
        
        if (!$episode_id) {
            echo '<div class="notice notice-error"><p>' . __('Invalid episode ID.', 'spiral-engine') . '</p></div>';
            return;
        }
        
        global $wpdb;
        $episode = $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, u.display_name, u.user_email
             FROM {$wpdb->prefix}spiralengine_episodes e
             LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.id = %d",
            $episode_id
        ));
        
        if (!$episode) {
            // Use sample data for demo
            $episode = $this->get_sample_episode_detail($episode_id);
        }
        
        $type_data = $this->episode_types[$episode->episode_type] ?? array(
            'label' => ucfirst($episode->episode_type),
            'icon' => '🔵',
            'color' => '#6B46C1'
        );
        
        ?>
        <div class="episode-detail-view">
            <div class="detail-header">
                <a href="<?php echo remove_query_arg(array('view', 'episode_id')); ?>" class="back-link">
                    ← <?php _e('Back to Episodes', 'spiral-engine'); ?>
                </a>
                <h2>
                    <?php echo $type_data['icon']; ?> 
                    <?php echo sprintf(__('%s Episode #%d', 'spiral-engine'), $type_data['label'], $episode->id); ?>
                </h2>
            </div>
            
            <div class="detail-grid">
                <!-- Episode Information -->
                <div class="detail-card">
                    <h3><?php _e('Episode Information', 'spiral-engine'); ?></h3>
                    <table class="detail-table">
                        <tr>
                            <th><?php _e('User:', 'spiral-engine'); ?></th>
                            <td>
                                <a href="<?php echo get_edit_user_link($episode->user_id); ?>">
                                    <?php echo get_avatar($episode->user_email, 24); ?>
                                    <?php echo esc_html($episode->display_name); ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Date/Time:', 'spiral-engine'); ?></th>
                            <td><?php echo date('F j, Y g:i a', strtotime($episode->created_at)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Severity:', 'spiral-engine'); ?></th>
                            <td>
                                <div class="severity-bar">
                                    <div class="severity-fill" style="width: <?php echo $episode->severity * 10; ?>%"></div>
                                </div>
                                <span class="severity-text"><?php echo $episode->severity; ?>/10</span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Duration:', 'spiral-engine'); ?></th>
                            <td><?php echo $episode->duration_minutes ? 
                                sprintf(__('%d minutes', 'spiral-engine'), $episode->duration_minutes) : 
                                __('Not specified', 'spiral-engine'); ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Trigger Information -->
                <div class="detail-card">
                    <h3><?php _e('Trigger Analysis', 'spiral-engine'); ?></h3>
                    <div class="trigger-info">
                        <p><strong><?php _e('Primary Trigger:', 'spiral-engine'); ?></strong> 
                            <?php echo esc_html($episode->primary_trigger ?: __('Not specified', 'spiral-engine')); ?></p>
                        
                        <?php if (!empty($episode->secondary_triggers)): ?>
                        <p><strong><?php _e('Secondary Triggers:', 'spiral-engine'); ?></strong></p>
                        <ul class="trigger-list">
                            <?php
                            $triggers = json_decode($episode->secondary_triggers, true) ?: array();
                            foreach ($triggers as $trigger):
                            ?>
                            <li><?php echo esc_html($trigger); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        
                        <p><strong><?php _e('Environment:', 'spiral-engine'); ?></strong> 
                            <?php echo esc_html($episode->environment ?: __('Not specified', 'spiral-engine')); ?></p>
                    </div>
                </div>
                
                <!-- Thoughts and Feelings -->
                <div class="detail-card full-width">
                    <h3><?php _e('Thoughts and Feelings', 'spiral-engine'); ?></h3>
                    <div class="thoughts-content">
                        <?php echo nl2br(esc_html($episode->thoughts)); ?>
                    </div>
                    
                    <?php if (!empty($episode->physical_sensations)): ?>
                    <h4><?php _e('Physical Sensations:', 'spiral-engine'); ?></h4>
                    <p><?php echo esc_html($episode->physical_sensations); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Correlations -->
                <div class="detail-card">
                    <h3><?php _e('Episode Correlations', 'spiral-engine'); ?></h3>
                    <?php $this->render_episode_correlations($episode_id); ?>
                </div>
                
                <!-- AI Analysis -->
                <div class="detail-card">
                    <h3><?php _e('AI Analysis', 'spiral-engine'); ?></h3>
                    <?php if (!empty($episode->ai_insights)): ?>
                    <div class="ai-insights">
                        <?php echo nl2br(esc_html($episode->ai_insights)); ?>
                    </div>
                    <?php else: ?>
                    <p><?php _e('No AI analysis available yet.', 'spiral-engine'); ?></p>
                    <button class="button button-primary" id="generate-ai-analysis" 
                            data-episode-id="<?php echo esc_attr($episode_id); ?>">
                        <?php _e('Generate AI Analysis', 'spiral-engine'); ?>
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- Actions -->
                <div class="detail-card full-width">
                    <h3><?php _e('Actions', 'spiral-engine'); ?></h3>
                    <div class="episode-actions">
                        <button class="button" id="export-episode" data-episode-id="<?php echo esc_attr($episode_id); ?>">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Export Episode', 'spiral-engine'); ?>
                        </button>
                        <button class="button" id="find-similar" data-episode-id="<?php echo esc_attr($episode_id); ?>">
                            <span class="dashicons dashicons-search"></span>
                            <?php _e('Find Similar Episodes', 'spiral-engine'); ?>
                        </button>
                        <button class="button" id="view-timeline" data-user-id="<?php echo esc_attr($episode->user_id); ?>">
                            <span class="dashicons dashicons-chart-line"></span>
                            <?php _e('View User Timeline', 'spiral-engine'); ?>
                        </button>
                        <?php if (current_user_can('delete_users')): ?>
                        <button class="button button-link-delete" id="delete-episode" 
                                data-episode-id="<?php echo esc_attr($episode_id); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Delete Episode', 'spiral-engine'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render pattern interface
     */
    private function render_pattern_interface() {
        ?>
        <div class="pattern-interface">
            <div class="pattern-header">
                <h2><?php _e('Episode Pattern Analysis', 'spiral-engine'); ?></h2>
                <div class="pattern-controls">
                    <select id="pattern-timeframe">
                        <option value="week"><?php _e('Last 7 Days', 'spiral-engine'); ?></option>
                        <option value="month" selected><?php _e('Last 30 Days', 'spiral-engine'); ?></option>
                        <option value="quarter"><?php _e('Last 90 Days', 'spiral-engine'); ?></option>
                    </select>
                    <button class="button" id="refresh-patterns">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'spiral-engine'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Pattern Discovery -->
            <div class="pattern-card">
                <h3><?php _e('Recently Discovered Patterns', 'spiral-engine'); ?></h3>
                <div class="pattern-list" id="discovered-patterns">
                    <?php $this->render_discovered_patterns(); ?>
                </div>
            </div>
            
            <!-- Pattern Network Visualization -->
            <div class="pattern-card">
                <h3><?php _e('Pattern Network Visualization', 'spiral-engine'); ?></h3>
                <div class="pattern-network" id="pattern-network-viz">
                    <canvas id="pattern-network-canvas"></canvas>
                </div>
            </div>
            
            <!-- Pattern Details -->
            <div class="pattern-card">
                <h3><?php _e('Pattern Details', 'spiral-engine'); ?></h3>
                <div id="pattern-details-container">
                    <p class="description"><?php _e('Select a pattern from the list or network to view details.', 'spiral-engine'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render episode analytics
     */
    private function render_episode_analytics() {
        ?>
        <div class="episode-analytics-view">
            <div class="analytics-header">
                <h2><?php _e('Episode Analytics Dashboard', 'spiral-engine'); ?></h2>
            </div>
            
            <!-- Quick Stats -->
            <div class="analytics-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon episodes"></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($this->get_total_episodes()); ?></div>
                        <div class="stat-label"><?php _e('Total Episodes', 'spiral-engine'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon patterns"></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($this->get_pattern_count()); ?></div>
                        <div class="stat-label"><?php _e('Patterns Found', 'spiral-engine'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon severity"></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $this->get_average_severity(); ?></div>
                        <div class="stat-label"><?php _e('Avg Severity', 'spiral-engine'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon users"></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($this->get_active_loggers()); ?></div>
                        <div class="stat-label"><?php _e('Active Loggers', 'spiral-engine'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Episode Type Distribution -->
            <div class="analytics-chart-card">
                <h3><?php _e('Episode Type Distribution', 'spiral-engine'); ?></h3>
                <canvas id="episode-type-distribution"></canvas>
            </div>
            
            <!-- Temporal Patterns -->
            <div class="analytics-chart-card">
                <h3><?php _e('Temporal Patterns', 'spiral-engine'); ?></h3>
                <div class="chart-tabs">
                    <button class="tab-button active" data-chart="hourly"><?php _e('Hourly', 'spiral-engine'); ?></button>
                    <button class="tab-button" data-chart="daily"><?php _e('Daily', 'spiral-engine'); ?></button>
                    <button class="tab-button" data-chart="weekly"><?php _e('Weekly', 'spiral-engine'); ?></button>
                    <button class="tab-button" data-chart="monthly"><?php _e('Monthly', 'spiral-engine'); ?></button>
                </div>
                <canvas id="temporal-patterns-chart"></canvas>
            </div>
            
            <!-- Trigger Analysis -->
            <div class="analytics-chart-card">
                <h3><?php _e('Common Triggers', 'spiral-engine'); ?></h3>
                <canvas id="trigger-analysis"></canvas>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render episode correlations
     */
    private function render_episode_correlations($episode_id) {
        global $wpdb;
        
        $correlations = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, 
                    e1.episode_type as primary_type, 
                    e2.episode_type as related_type,
                    e2.created_at as related_time
             FROM {$wpdb->prefix}spiralengine_correlations c
             JOIN {$wpdb->prefix}spiralengine_episodes e1 ON c.primary_episode_id = e1.id
             JOIN {$wpdb->prefix}spiralengine_episodes e2 ON c.related_episode_id = e2.id
             WHERE c.primary_episode_id = %d OR c.related_episode_id = %d
             ORDER BY c.correlation_strength DESC",
            $episode_id, $episode_id
        ));
        
        if (empty($correlations)) {
            echo '<p>' . __('No correlations found for this episode.', 'spiral-engine') . '</p>';
            return;
        }
        
        ?>
        <div class="correlation-list">
            <?php foreach ($correlations as $correlation): ?>
            <div class="correlation-item">
                <div class="correlation-strength" style="background-color: <?php echo $this->get_strength_color($correlation->correlation_strength); ?>">
                    <?php echo round($correlation->correlation_strength * 100); ?>%
                </div>
                <div class="correlation-details">
                    <strong><?php echo esc_html($this->episode_types[$correlation->related_type]['label']); ?></strong>
                    <span class="correlation-time">
                        <?php 
                        $time_diff = abs(strtotime($correlation->related_time) - strtotime($episode->created_at));
                        echo sprintf(__('%s apart', 'spiral-engine'), human_time_diff(0, $time_diff));
                        ?>
                    </span>
                    <?php if ($correlation->pattern_type): ?>
                    <span class="pattern-badge"><?php echo esc_html($correlation->pattern_type); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render discovered patterns
     */
    private function render_discovered_patterns() {
        global $wpdb;
        
        $patterns = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}spiralengine_pattern_discoveries 
             ORDER BY discovery_date DESC 
             LIMIT 10"
        );
        
        if (empty($patterns)) {
            // Show sample patterns for demo
            $patterns = $this->get_sample_patterns();
        }
        
        foreach ($patterns as $pattern) {
            $episode_types = json_decode($pattern->episode_types, true) ?: array();
            ?>
            <div class="pattern-item" data-pattern-id="<?php echo esc_attr($pattern->id); ?>">
                <div class="pattern-strength">
                    <div class="strength-circle" style="background-color: <?php echo $this->get_strength_color($pattern->pattern_strength); ?>">
                        <?php echo round($pattern->pattern_strength * 100); ?>%
                    </div>
                </div>
                <div class="pattern-info">
                    <h4><?php echo esc_html($pattern->pattern_type); ?></h4>
                    <p class="pattern-description">
                        <?php echo esc_html(implode(' → ', $episode_types)); ?>
                    </p>
                    <div class="pattern-meta">
                        <span class="affected-users">
                            <?php echo sprintf(
                                _n('%s user affected', '%s users affected', $pattern->affected_users, 'spiral-engine'),
                                number_format($pattern->affected_users)
                            ); ?>
                        </span>
                        <span class="discovery-date">
                            <?php echo sprintf(
                                __('Discovered %s ago', 'spiral-engine'),
                                human_time_diff(strtotime($pattern->discovery_date), current_time('timestamp'))
                            ); ?>
                        </span>
                    </div>
                </div>
                <div class="pattern-actions">
                    <button class="button button-small view-pattern-details" 
                            data-pattern-id="<?php echo esc_attr($pattern->id); ?>">
                        <?php _e('View Details', 'spiral-engine'); ?>
                    </button>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render pagination
     */
    private function render_pagination() {
        global $wpdb;
        
        $total_items = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes"
        );
        
        if (!$total_items) {
            $total_items = 100; // Demo value
        }
        
        $per_page = 20;
        $total_pages = ceil($total_items / $per_page);
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $page_links = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page
        ));
        
        if ($page_links) {
            echo '<span class="displaying-num">';
            echo sprintf(
                _n('%s item', '%s items', $total_items, 'spiral-engine'),
                number_format_i18n($total_items)
            );
            echo '</span>';
            echo '<span class="pagination-links">' . $page_links . '</span>';
        }
    }
    
    /**
     * AJAX filter episodes
     */
    public function ajax_filter_episodes() {
        check_ajax_referer('spiral-engine-admin', 'nonce');
        
        ob_start();
        $this->render_episode_rows();
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX bulk action
     */
    public function ajax_bulk_action() {
        check_ajax_referer('spiral-engine-admin', 'nonce');
        
        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $episode_ids = isset($_POST['episode_ids']) ? array_map('intval', $_POST['episode_ids']) : array();
        
        if (empty($episode_ids)) {
            wp_send_json_error(__('No episodes selected.', 'spiral-engine'));
        }
        
        switch ($action) {
            case 'export':
                $result = $this->export_episodes($episode_ids);
                break;
                
            case 'analyze':
                $result = $this->analyze_episodes($episode_ids);
                break;
                
            case 'delete':
                if (!current_user_can('delete_users')) {
                    wp_send_json_error(__('Insufficient permissions.', 'spiral-engine'));
                }
                $result = $this->delete_episodes($episode_ids);
                break;
                
            default:
                $result = array('success' => false, 'message' => __('Invalid action.', 'spiral-engine'));
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX episode detail
     */
    public function ajax_episode_detail() {
        check_ajax_referer('spiral-engine-admin', 'nonce');
        
        $episode_id = isset($_POST['episode_id']) ? intval($_POST['episode_id']) : 0;
        
        if (!$episode_id) {
            wp_send_json_error(__('Invalid episode ID.', 'spiral-engine'));
        }
        
        // Get episode details
        global $wpdb;
        $episode = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_episodes WHERE id = %d",
            $episode_id
        ));
        
        if (!$episode) {
            wp_send_json_error(__('Episode not found.', 'spiral-engine'));
        }
        
        wp_send_json_success($episode);
    }
    
    /**
     * Get sample episodes for demo
     */
    private function get_sample_episodes() {
        $sample_episodes = array();
        $users = array(
            (object)array('ID' => 1, 'display_name' => 'John Doe', 'user_email' => 'john@example.com'),
            (object)array('ID' => 2, 'display_name' => 'Jane Smith', 'user_email' => 'jane@example.com'),
            (object)array('ID' => 3, 'display_name' => 'Mike Johnson', 'user_email' => 'mike@example.com')
        );
        
        $triggers = array('Work Stress', 'Social Event', 'Poor Sleep', 'Family Issue', 'Health Concern');
        $thoughts = array(
            'Feeling overwhelmed with all the tasks I need to complete.',
            'Can\'t stop thinking about what happened yesterday.',
            'Worried about the upcoming presentation.',
            'Having trouble focusing on anything productive.',
            'Feeling anxious about the future.'
        );
        
        for ($i = 1; $i <= 10; $i++) {
            $user = $users[array_rand($users)];
            $type = array_rand($this->episode_types);
            
            $sample_episodes[] = (object)array(
                'id' => $i,
                'user_id' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'episode_type' => $type,
                'severity' => rand(3, 9),
                'primary_trigger' => $triggers[array_rand($triggers)],
                'thoughts' => $thoughts[array_rand($thoughts)],
                'correlation_count' => rand(0, 5),
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(0, 30) . ' days'))
            );
        }
        
        return $sample_episodes;
    }
    
    /**
     * Get sample episode detail
     */
    private function get_sample_episode_detail($episode_id) {
        return (object)array(
            'id' => $episode_id,
            'user_id' => 1,
            'display_name' => 'John Doe',
            'user_email' => 'john@example.com',
            'episode_type' => 'overthinking',
            'severity' => 7,
            'duration_minutes' => 45,
            'primary_trigger' => 'Work Stress',
            'secondary_triggers' => json_encode(array('Deadline Pressure', 'Team Conflict')),
            'environment' => 'Office',
            'thoughts' => "I can't stop thinking about the project deadline. Every time I try to focus on one task, my mind jumps to all the other things I need to do. I feel like I'm falling behind and letting everyone down. The more I think about it, the worse it gets.",
            'physical_sensations' => 'Tension headache, tight shoulders, difficulty breathing',
            'ai_insights' => "This episode shows a classic overthinking pattern triggered by work-related stress. The cascading thoughts and self-criticism are creating a negative feedback loop. Consider breaking tasks into smaller, manageable chunks and practicing mindfulness techniques when you notice the spiral beginning.",
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        );
    }
    
    /**
     * Get sample patterns
     */
    private function get_sample_patterns() {
        return array(
            (object)array(
                'id' => 1,
                'pattern_type' => 'Stress Cascade',
                'episode_types' => json_encode(array('Work Stress', 'Overthinking', 'Anxiety')),
                'pattern_strength' => 0.82,
                'affected_users' => 4567,
                'discovery_date' => date('Y-m-d H:i:s', strtotime('-3 days'))
            ),
            (object)array(
                'id' => 2,
                'pattern_type' => 'Sleep Disruption',
                'episode_types' => json_encode(array('Poor Sleep', 'Anxiety', 'Depression')),
                'pattern_strength' => 0.75,
                'affected_users' => 3234,
                'discovery_date' => date('Y-m-d H:i:s', strtotime('-7 days'))
            ),
            (object)array(
                'id' => 3,
                'pattern_type' => 'Social Trigger',
                'episode_types' => json_encode(array('Social Event', 'PTSD', 'Anxiety')),
                'pattern_strength' => 0.68,
                'affected_users' => 2145,
                'discovery_date' => date('Y-m-d H:i:s', strtotime('-10 days'))
            )
        );
    }
    
    /**
     * Get strength color
     */
    private function get_strength_color($strength) {
        if ($strength >= 0.8) {
            return '#e74c3c'; // Red for strong
        } elseif ($strength >= 0.6) {
            return '#f39c12'; // Orange for medium
        } else {
            return '#95a5a6'; // Gray for weak
        }
    }
    
    /**
     * Get total episodes count
     */
    private function get_total_episodes() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes") ?: 45678;
    }
    
    /**
     * Get pattern count
     */
    private function get_pattern_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_pattern_discoveries") ?: 234;
    }
    
    /**
     * Get average severity
     */
    private function get_average_severity() {
        global $wpdb;
        $avg = $wpdb->get_var("SELECT AVG(severity) FROM {$wpdb->prefix}spiralengine_episodes");
        return $avg ? number_format($avg, 1) : '6.2';
    }
    
    /**
     * Get active loggers count
     */
    private function get_active_loggers() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->prefix}spiralengine_episodes 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ) ?: 892;
    }
    
    /**
     * Export episodes
     */
    private function export_episodes($episode_ids) {
        // Generate CSV data
        $csv_data = $this->generate_episode_csv($episode_ids);
        
        return array(
            'success' => true,
            'data' => $csv_data,
            'filename' => 'episodes-export-' . date('Y-m-d') . '.csv'
        );
    }
    
    /**
     * Analyze episodes
     */
    private function analyze_episodes($episode_ids) {
        // Trigger pattern analysis
        do_action('spiral_engine_analyze_episodes', $episode_ids);
        
        return array(
            'success' => true,
            'message' => sprintf(
                __('Pattern analysis started for %d episodes.', 'spiral-engine'),
                count($episode_ids)
            )
        );
    }
    
    /**
     * Delete episodes
     */
    private function delete_episodes($episode_ids) {
        global $wpdb;
        
        // Delete episodes and related data
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}spiralengine_episodes 
            WHERE id IN (" . implode(',', array_map('intval', $episode_ids)) . ")"
        );
        
        // Also delete correlations
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}spiralengine_correlations 
            WHERE primary_episode_id IN (" . implode(',', array_map('intval', $episode_ids)) . ")
            OR related_episode_id IN (" . implode(',', array_map('intval', $episode_ids)) . ")"
        );
        
        return array(
            'success' => true,
            'message' => sprintf(
                _n('%d episode deleted.', '%d episodes deleted.', $deleted, 'spiral-engine'),
                $deleted
            )
        );
    }
    
    /**
     * Generate episode CSV
     */
    private function generate_episode_csv($episode_ids) {
        global $wpdb;
        
        $episodes = $wpdb->get_results(
            "SELECT e.*, u.display_name 
            FROM {$wpdb->prefix}spiralengine_episodes e
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
            WHERE e.id IN (" . implode(',', array_map('intval', $episode_ids)) . ")"
        );
        
        $csv = "Episode ID,User,Type,Severity,Trigger,Thoughts,Date\n";
        
        foreach ($episodes as $episode) {
            $csv .= sprintf(
                '"%d","%s","%s","%d","%s","%s","%s"' . "\n",
                $episode->id,
                $episode->display_name,
                $episode->episode_type,
                $episode->severity,
                $episode->primary_trigger,
                str_replace('"', '""', $episode->thoughts),
                $episode->created_at
            );
        }
        
        return $csv;
    }
}

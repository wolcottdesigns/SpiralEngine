<?php
/**
 * SpiralEngine User Management Interface
 *
 * @package    SpiralEngine
 * @subpackage Admin
 * @file       includes/admin/class-user-management.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SpiralEngine_User_Management
 *
 * Comprehensive user management system
 */
class SpiralEngine_User_Management {
    
    /**
     * Instance of this class
     *
     * @var SpiralEngine_User_Management
     */
    private static $instance = null;
    
    /**
     * List table instance
     *
     * @var SpiralEngine_Users_List_Table
     */
    private $list_table = null;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Get instance
     *
     * @return SpiralEngine_User_Management
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize user management
     */
    public function init() {
        add_action('admin_init', array($this, 'process_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_spiralengine_user_search', array($this, 'ajax_user_search'));
        add_action('wp_ajax_spiralengine_update_user_tier', array($this, 'ajax_update_tier'));
        add_action('wp_ajax_spiralengine_get_user_details', array($this, 'ajax_get_user_details'));
        add_action('wp_ajax_spiralengine_impersonate_user', array($this, 'ajax_impersonate_user'));
        add_action('wp_ajax_spiralengine_bulk_user_action', array($this, 'ajax_bulk_action'));
    }
    
    /**
     * Enqueue scripts and styles
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'spiralengine-users') === false) {
            return;
        }
        
        wp_enqueue_style('spiralengine-admin');
        wp_enqueue_script('spiralengine-admin');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Add user management specific script
        wp_add_inline_script('spiralengine-admin', $this->get_inline_script());
        
        // Localize script
        wp_localize_script('spiralengine-admin', 'spiralengine_user_mgmt', array(
            'nonce' => wp_create_nonce('spiralengine_user_management'),
            'strings' => array(
                'confirm_impersonate' => __('Are you sure you want to impersonate this user?', 'spiralengine'),
                'confirm_delete_data' => __('Are you sure you want to delete all data for this user? This cannot be undone!', 'spiralengine'),
                'confirm_bulk_action' => __('Are you sure you want to perform this action on {count} users?', 'spiralengine'),
                'processing' => __('Processing...', 'spiralengine'),
                'error' => __('An error occurred. Please try again.', 'spiralengine')
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
            // User quick view
            $('.spiralengine-user-quick-view').on('click', function(e) {
                e.preventDefault();
                var userId = $(this).data('user-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_get_user_details',
                        user_id: userId,
                        nonce: spiralengine_user_mgmt.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('<div>' + response.data.html + '</div>').dialog({
                                title: response.data.title,
                                width: 600,
                                modal: true,
                                buttons: {
                                    Close: function() {
                                        $(this).dialog('close');
                                    }
                                }
                            });
                        }
                    }
                });
            });
            
            // Inline tier editing
            $('.spiralengine-tier-select').on('change', function() {
                var select = $(this);
                var userId = select.data('user-id');
                var newTier = select.val();
                var originalValue = select.data('original');
                
                select.prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_update_user_tier',
                        user_id: userId,
                        tier: newTier,
                        nonce: spiralengine_user_mgmt.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            select.data('original', newTier);
                            select.parent().find('.dashicons-yes').fadeIn().delay(1000).fadeOut();
                        } else {
                            select.val(originalValue);
                            alert(response.data.message || spiralengine_user_mgmt.strings.error);
                        }
                    },
                    complete: function() {
                        select.prop('disabled', false);
                    }
                });
            });
            
            // Impersonate user
            $('.spiralengine-impersonate').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm(spiralengine_user_mgmt.strings.confirm_impersonate)) {
                    return;
                }
                
                var userId = $(this).data('user-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_impersonate_user',
                        user_id: userId,
                        nonce: spiralengine_user_mgmt.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            alert(response.data.message || spiralengine_user_mgmt.strings.error);
                        }
                    }
                });
            });
            
            // User search autocomplete
            $('#spiralengine-user-search').autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'spiralengine_user_search',
                            term: request.term,
                            nonce: spiralengine_user_mgmt.nonce
                        },
                        success: function(data) {
                            response(data.data);
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    window.location.href = ui.item.url;
                }
            });
            
            // Advanced filters toggle
            $('#spiralengine-toggle-filters').on('click', function(e) {
                e.preventDefault();
                $('.spiralengine-advanced-filters').slideToggle();
                $(this).find('.dashicons').toggleClass('dashicons-arrow-down dashicons-arrow-up');
            });
            
            // Export users
            $('#spiralengine-export-users').on('click', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var format = $('#export-format').val();
                var filters = $('#users-filter-form').serialize();
                
                button.prop('disabled', true).text(spiralengine_user_mgmt.strings.processing);
                
                // Create download URL
                var exportUrl = ajaxurl + '?action=spiralengine_export_users&format=' + format + '&' + filters + '&nonce=' + spiralengine_user_mgmt.nonce;
                
                // Trigger download
                window.location.href = exportUrl;
                
                // Reset button
                setTimeout(function() {
                    button.prop('disabled', false).text(button.data('original-text'));
                }, 2000);
            });
        });
        ";
    }
    
    /**
     * Render user management page
     */
    public function render_page() {
        if (!current_user_can('spiralengine_manage_users')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'spiralengine'));
        }
        
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        
        switch ($action) {
            case 'edit':
                $this->render_edit_user();
                break;
                
            case 'new':
                $this->render_new_user();
                break;
                
            case 'import':
                $this->render_import_users();
                break;
                
            default:
                $this->render_user_list();
                break;
        }
    }
    
    /**
     * Render user list view
     */
    private function render_user_list() {
        // Load list table if not loaded
        if (!class_exists('SpiralEngine_Users_List_Table')) {
            require_once SPIRALENGINE_PLUGIN_DIR . 'admin/class-spiralengine-users-list-table.php';
        }
        
        $this->list_table = new SpiralEngine_Users_List_Table();
        $this->list_table->prepare_items();
        
        ?>
        <div class="wrap spiralengine-users-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-groups"></span>
                <?php _e('SpiralEngine Users', 'spiralengine'); ?>
            </h1>
            
            <a href="<?php echo admin_url('admin.php?page=spiralengine-users&action=new'); ?>" class="page-title-action">
                <?php _e('Add New User', 'spiralengine'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=spiralengine-users&action=import'); ?>" class="page-title-action">
                <?php _e('Import Users', 'spiralengine'); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <?php $this->render_user_stats(); ?>
            
            <div class="spiralengine-user-filters">
                <button id="spiralengine-toggle-filters" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-down"></span>
                    <?php _e('Advanced Filters', 'spiralengine'); ?>
                </button>
                
                <div class="spiralengine-advanced-filters" style="display: none;">
                    <?php $this->render_advanced_filters(); ?>
                </div>
            </div>
            
            <form id="users-filter-form" method="get">
                <input type="hidden" name="page" value="spiralengine-users" />
                
                <div class="spiralengine-search-box">
                    <input type="text" 
                           id="spiralengine-user-search" 
                           placeholder="<?php esc_attr_e('Search users by name or email...', 'spiralengine'); ?>" 
                           class="regular-text" />
                </div>
                
                <?php $this->list_table->display(); ?>
            </form>
            
            <div class="spiralengine-bulk-actions-bottom">
                <select id="export-format">
                    <option value="csv"><?php _e('CSV', 'spiralengine'); ?></option>
                    <option value="json"><?php _e('JSON', 'spiralengine'); ?></option>
                    <option value="xlsx"><?php _e('Excel', 'spiralengine'); ?></option>
                </select>
                
                <button id="spiralengine-export-users" class="button button-secondary" data-original-text="<?php esc_attr_e('Export Users', 'spiralengine'); ?>">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Users', 'spiralengine'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render user statistics
     */
    private function render_user_stats() {
        global $wpdb;
        
        // Get tier counts
        $tier_counts = $wpdb->get_results(
            "SELECT tier, COUNT(*) as count 
            FROM {$wpdb->prefix}spiralengine_memberships 
            WHERE status = 'active' 
            GROUP BY tier",
            OBJECT_K
        );
        
        // Get total episode count
        $total_episodes = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes"
        );
        
        // Get active users (last 30 days)
        $active_users = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->prefix}spiralengine_episodes 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        ?>
        <div class="spiralengine-user-stats">
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format(array_sum(wp_list_pluck($tier_counts, 'count'))); ?></span>
                <span class="stat-label"><?php _e('Total Users', 'spiralengine'); ?></span>
            </div>
            
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($active_users); ?></span>
                <span class="stat-label"><?php _e('Active (30d)', 'spiralengine'); ?></span>
            </div>
            
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($total_episodes); ?></span>
                <span class="stat-label"><?php _e('Total Episodes', 'spiralengine'); ?></span>
            </div>
            
            <?php
            $tiers = array(
                'free' => __('Free', 'spiralengine'),
                'bronze' => __('Bronze', 'spiralengine'),
                'silver' => __('Silver', 'spiralengine'),
                'gold' => __('Gold', 'spiralengine'),
                'platinum' => __('Platinum', 'spiralengine')
            );
            
            foreach ($tiers as $tier_key => $tier_name) :
                $count = isset($tier_counts[$tier_key]) ? $tier_counts[$tier_key]->count : 0;
                ?>
                <div class="stat-card tier-stat tier-<?php echo esc_attr($tier_key); ?>">
                    <span class="stat-value"><?php echo number_format($count); ?></span>
                    <span class="stat-label"><?php echo $tier_name; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render advanced filters
     */
    private function render_advanced_filters() {
        ?>
        <div class="spiralengine-filter-row">
            <div class="filter-group">
                <label><?php _e('Registration Date', 'spiralengine'); ?></label>
                <input type="date" name="reg_date_from" placeholder="<?php esc_attr_e('From', 'spiralengine'); ?>" />
                <input type="date" name="reg_date_to" placeholder="<?php esc_attr_e('To', 'spiralengine'); ?>" />
            </div>
            
            <div class="filter-group">
                <label><?php _e('Last Activity', 'spiralengine'); ?></label>
                <select name="last_activity">
                    <option value=""><?php _e('Any time', 'spiralengine'); ?></option>
                    <option value="7"><?php _e('Last 7 days', 'spiralengine'); ?></option>
                    <option value="30"><?php _e('Last 30 days', 'spiralengine'); ?></option>
                    <option value="90"><?php _e('Last 90 days', 'spiralengine'); ?></option>
                    <option value="inactive"><?php _e('Inactive (90+ days)', 'spiralengine'); ?></option>
                </select>
            </div>
            
            <div class="filter-group">
                <label><?php _e('Episode Count', 'spiralengine'); ?></label>
                <input type="number" name="episodes_min" placeholder="<?php esc_attr_e('Min', 'spiralengine'); ?>" class="small-text" />
                <input type="number" name="episodes_max" placeholder="<?php esc_attr_e('Max', 'spiralengine'); ?>" class="small-text" />
            </div>
            
            <div class="filter-group">
                <label><?php _e('Has AI Access', 'spiralengine'); ?></label>
                <select name="has_ai">
                    <option value=""><?php _e('All', 'spiralengine'); ?></option>
                    <option value="yes"><?php _e('Yes', 'spiralengine'); ?></option>
                    <option value="no"><?php _e('No', 'spiralengine'); ?></option>
                </select>
            </div>
            
            <div class="filter-group">
                <label><?php _e('Custom Limits', 'spiralengine'); ?></label>
                <select name="custom_limits">
                    <option value=""><?php _e('All', 'spiralengine'); ?></option>
                    <option value="has"><?php _e('Has custom limits', 'spiralengine'); ?></option>
                    <option value="none"><?php _e('No custom limits', 'spiralengine'); ?></option>
                </select>
            </div>
            
            <div class="filter-group">
                <button type="submit" class="button button-primary"><?php _e('Apply Filters', 'spiralengine'); ?></button>
                <a href="<?php echo admin_url('admin.php?page=spiralengine-users'); ?>" class="button button-secondary">
                    <?php _e('Clear Filters', 'spiralengine'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render edit user page
     */
    private function render_edit_user() {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        if (!$user_id) {
            wp_die(__('Invalid user ID.', 'spiralengine'));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_die(__('User not found.', 'spiralengine'));
        }
        
        // Get membership data
        $membership = SpiralEngine_Membership::get_user_membership($user_id);
        
        // Get user statistics
        global $wpdb;
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_episodes,
                MAX(created_at) as last_episode,
                AVG(severity) as avg_severity
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d",
            $user_id
        ));
        
        ?>
        <div class="wrap spiralengine-edit-user">
            <h1>
                <?php _e('Edit User', 'spiralengine'); ?>: 
                <?php echo esc_html($user->display_name); ?>
            </h1>
            
            <div class="spiralengine-user-header">
                <?php echo get_avatar($user_id, 64); ?>
                <div class="user-info">
                    <h2><?php echo esc_html($user->display_name); ?></h2>
                    <p><?php echo esc_html($user->user_email); ?></p>
                    <p class="user-meta">
                        <?php _e('Member since', 'spiralengine'); ?>: 
                        <?php echo date_i18n(get_option('date_format'), strtotime($user->user_registered)); ?>
                    </p>
                </div>
                
                <div class="user-actions">
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=spiralengine-users&action=impersonate&user_id=' . $user_id), 'impersonate_user_' . $user_id); ?>" 
                       class="button button-secondary">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php _e('Impersonate User', 'spiralengine'); ?>
                    </a>
                    
                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $user_id); ?>" 
                       class="button button-secondary">
                        <span class="dashicons dashicons-edit"></span>
                        <?php _e('Edit in WordPress', 'spiralengine'); ?>
                    </a>
                </div>
            </div>
            
            <div class="spiralengine-user-content">
                <div class="user-column-left">
                    <!-- Membership Information -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Membership Information', 'spiralengine'); ?></h2>
                        <div class="inside">
                            <form method="post" action="">
                                <?php wp_nonce_field('spiralengine_update_user_' . $user_id); ?>
                                <input type="hidden" name="action" value="update_membership" />
                                
                                <table class="form-table">
                                    <tr>
                                        <th><?php _e('Current Tier', 'spiralengine'); ?></th>
                                        <td>
                                            <select name="tier" class="regular-text">
                                                <?php
                                                $tiers = array(
                                                    'free' => __('Free', 'spiralengine'),
                                                    'bronze' => __('Bronze', 'spiralengine'),
                                                    'silver' => __('Silver', 'spiralengine'),
                                                    'gold' => __('Gold', 'spiralengine'),
                                                    'platinum' => __('Platinum', 'spiralengine'),
                                                    'custom' => __('Custom', 'spiralengine')
                                                );
                                                
                                                foreach ($tiers as $tier_key => $tier_name) {
                                                    printf(
                                                        '<option value="%s" %s>%s</option>',
                                                        esc_attr($tier_key),
                                                        selected($membership->tier, $tier_key, false),
                                                        esc_html($tier_name)
                                                    );
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><?php _e('Status', 'spiralengine'); ?></th>
                                        <td>
                                            <select name="status">
                                                <option value="active" <?php selected($membership->status, 'active'); ?>>
                                                    <?php _e('Active', 'spiralengine'); ?>
                                                </option>
                                                <option value="suspended" <?php selected($membership->status, 'suspended'); ?>>
                                                    <?php _e('Suspended', 'spiralengine'); ?>
                                                </option>
                                                <option value="cancelled" <?php selected($membership->status, 'cancelled'); ?>>
                                                    <?php _e('Cancelled', 'spiralengine'); ?>
                                                </option>
                                            </select>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><?php _e('Expires', 'spiralengine'); ?></th>
                                        <td>
                                            <input type="date" 
                                                   name="expires_at" 
                                                   value="<?php echo $membership->expires_at ? date('Y-m-d', strtotime($membership->expires_at)) : ''; ?>" />
                                            <p class="description"><?php _e('Leave empty for no expiration', 'spiralengine'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <h3><?php _e('Custom Limits', 'spiralengine'); ?></h3>
                                
                                <table class="form-table">
                                    <tr>
                                        <th><?php _e('Episode Limit', 'spiralengine'); ?></th>
                                        <td>
                                            <input type="number" 
                                                   name="custom_limits[episode_limit]" 
                                                   value="<?php echo isset($membership->custom_limits['episode_limit']) ? $membership->custom_limits['episode_limit'] : ''; ?>" 
                                                   min="-1" />
                                            <p class="description"><?php _e('Episodes per month (-1 for unlimited, empty for tier default)', 'spiralengine'); ?></p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><?php _e('Widget Limit', 'spiralengine'); ?></th>
                                        <td>
                                            <input type="number" 
                                                   name="custom_limits[widget_limit]" 
                                                   value="<?php echo isset($membership->custom_limits['widget_limit']) ? $membership->custom_limits['widget_limit'] : ''; ?>" 
                                                   min="-1" />
                                            <p class="description"><?php _e('Number of widgets (-1 for all, empty for tier default)', 'spiralengine'); ?></p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th><?php _e('AI Requests', 'spiralengine'); ?></th>
                                        <td>
                                            <input type="number" 
                                                   name="custom_limits[ai_requests]" 
                                                   value="<?php echo isset($membership->custom_limits['ai_requests']) ? $membership->custom_limits['ai_requests'] : ''; ?>" 
                                                   min="0" />
                                            <p class="description"><?php _e('AI requests per month (empty for tier default)', 'spiralengine'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <?php submit_button(__('Update Membership', 'spiralengine')); ?>
                            </form>
                        </div>
                    </div>
                    
                    <!-- User Notes -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Admin Notes', 'spiralengine'); ?></h2>
                        <div class="inside">
                            <form method="post" action="">
                                <?php wp_nonce_field('spiralengine_user_notes_' . $user_id); ?>
                                <input type="hidden" name="action" value="update_notes" />
                                
                                <textarea name="admin_notes" 
                                          rows="5" 
                                          class="large-text"><?php echo esc_textarea(get_user_meta($user_id, 'spiralengine_admin_notes', true)); ?></textarea>
                                
                                <p><?php submit_button(__('Save Notes', 'spiralengine'), 'secondary', 'submit', false); ?></p>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="user-column-right">
                    <!-- User Statistics -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('User Statistics', 'spiralengine'); ?></h2>
                        <div class="inside">
                            <table class="spiralengine-stats-table">
                                <tr>
                                    <th><?php _e('Total Episodes', 'spiralengine'); ?></th>
                                    <td><?php echo number_format($stats->total_episodes); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Last Episode', 'spiralengine'); ?></th>
                                    <td>
                                        <?php 
                                        echo $stats->last_episode 
                                            ? human_time_diff(strtotime($stats->last_episode), current_time('timestamp')) . ' ' . __('ago', 'spiralengine')
                                            : __('Never', 'spiralengine');
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('Average Severity', 'spiralengine'); ?></th>
                                    <td><?php echo $stats->avg_severity ? number_format($stats->avg_severity, 1) : 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('This Month', 'spiralengine'); ?></th>
                                    <td>
                                        <?php
                                        $this_month = $wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes 
                                            WHERE user_id = %d AND MONTH(created_at) = MONTH(CURRENT_DATE())",
                                            $user_id
                                        ));
                                        echo number_format($this_month);
                                        ?>
                                    </td>
                                </tr>
                            </table>
                            
                            <p>
                                <a href="<?php echo admin_url('admin.php?page=spiralengine-episodes&user_id=' . $user_id); ?>" 
                                   class="button button-secondary">
                                    <?php _e('View All Episodes', 'spiralengine'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Recent Activity', 'spiralengine'); ?></h2>
                        <div class="inside">
                            <?php
                            $recent_episodes = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}spiralengine_episodes 
                                WHERE user_id = %d 
                                ORDER BY created_at DESC 
                                LIMIT 5",
                                $user_id
                            ));
                            
                            if ($recent_episodes) :
                                ?>
                                <ul class="spiralengine-activity-list">
                                    <?php foreach ($recent_episodes as $episode) : ?>
                                        <li>
                                            <strong><?php echo esc_html($episode->widget_id); ?></strong>
                                            <span class="severity severity-<?php echo $episode->severity; ?>">
                                                <?php echo $episode->severity; ?>/10
                                            </span>
                                            <br />
                                            <small><?php echo human_time_diff(strtotime($episode->created_at), current_time('timestamp')); ?> <?php _e('ago', 'spiralengine'); ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p><?php _e('No recent activity.', 'spiralengine'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Danger Zone -->
                    <div class="postbox danger-zone">
                        <h2 class="hndle" style="color: #d63638;"><?php _e('Danger Zone', 'spiralengine'); ?></h2>
                        <div class="inside">
                            <p><?php _e('These actions are permanent and cannot be undone.', 'spiralengine'); ?></p>
                            
                            <p>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=spiralengine-users&action=delete_data&user_id=' . $user_id), 'delete_user_data_' . $user_id); ?>" 
                                   class="button button-secondary delete-data" 
                                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete all data for this user? This cannot be undone!', 'spiralengine'); ?>');">
                                    <?php _e('Delete All User Data', 'spiralengine'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render new user page
     */
    private function render_new_user() {
        ?>
        <div class="wrap">
            <h1><?php _e('Add New SpiralEngine User', 'spiralengine'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('spiralengine_create_user'); ?>
                <input type="hidden" name="action" value="create_user" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="user_login"><?php _e('Username', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="user_login" name="user_login" class="regular-text" required />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="user_email"><?php _e('Email', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="user_email" name="user_email" class="regular-text" required />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="first_name"><?php _e('First Name', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="first_name" name="first_name" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="last_name"><?php _e('Last Name', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="last_name" name="last_name" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="membership_tier"><?php _e('Membership Tier', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <select id="membership_tier" name="membership_tier">
                                <option value="free"><?php _e('Free', 'spiralengine'); ?></option>
                                <option value="bronze"><?php _e('Bronze', 'spiralengine'); ?></option>
                                <option value="silver"><?php _e('Silver', 'spiralengine'); ?></option>
                                <option value="gold"><?php _e('Gold', 'spiralengine'); ?></option>
                                <option value="platinum"><?php _e('Platinum', 'spiralengine'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="send_welcome"><?php _e('Send Welcome Email', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="send_welcome" name="send_welcome" value="1" checked />
                            <label for="send_welcome"><?php _e('Send welcome email to new user', 'spiralengine'); ?></label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Create User', 'spiralengine')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render import users page
     */
    private function render_import_users() {
        ?>
        <div class="wrap">
            <h1><?php _e('Import Users', 'spiralengine'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('Import users from a CSV file. The file should contain columns: email, first_name, last_name, tier', 'spiralengine'); ?></p>
            </div>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('spiralengine_import_users'); ?>
                <input type="hidden" name="action" value="import_users" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="import_file"><?php _e('CSV File', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="import_file" name="import_file" accept=".csv" required />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_tier"><?php _e('Default Tier', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <select id="default_tier" name="default_tier">
                                <option value="free"><?php _e('Free', 'spiralengine'); ?></option>
                                <option value="bronze"><?php _e('Bronze', 'spiralengine'); ?></option>
                                <option value="silver"><?php _e('Silver', 'spiralengine'); ?></option>
                                <option value="gold"><?php _e('Gold', 'spiralengine'); ?></option>
                                <option value="platinum"><?php _e('Platinum', 'spiralengine'); ?></option>
                            </select>
                            <p class="description"><?php _e('Used when tier is not specified in CSV', 'spiralengine'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php _e('Options', 'spiralengine'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="update_existing" value="1" />
                                <?php _e('Update existing users', 'spiralengine'); ?>
                            </label>
                            <br />
                            
                            <label>
                                <input type="checkbox" name="send_welcome" value="1" />
                                <?php _e('Send welcome emails', 'spiralengine'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Import Users', 'spiralengine')); ?>
            </form>
            
            <h3><?php _e('Sample CSV Format', 'spiralengine'); ?></h3>
            <pre>
email,first_name,last_name,tier
john@example.com,John,Doe,silver
jane@example.com,Jane,Smith,gold
bob@example.com,Bob,Johnson,free
            </pre>
        </div>
        <?php
    }
    
    /**
     * Process admin actions
     */
    public function process_actions() {
        if (!isset($_POST['action']) && !isset($_GET['action'])) {
            return;
        }
        
        $action = isset($_POST['action']) ? $_POST['action'] : $_GET['action'];
        
        switch ($action) {
            case 'update_membership':
                $this->process_update_membership();
                break;
                
            case 'update_notes':
                $this->process_update_notes();
                break;
                
            case 'create_user':
                $this->process_create_user();
                break;
                
            case 'import_users':
                $this->process_import_users();
                break;
                
            case 'impersonate':
                $this->process_impersonate();
                break;
                
            case 'delete_data':
                $this->process_delete_data();
                break;
        }
    }
    
    /**
     * Process membership update
     */
    private function process_update_membership() {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'spiralengine_update_user_' . $user_id)) {
            wp_die(__('Security check failed.', 'spiralengine'));
        }
        
        $membership_data = array(
            'tier' => sanitize_key($_POST['tier']),
            'status' => sanitize_key($_POST['status']),
            'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' 23:59:59' : null
        );
        
        // Handle custom limits
        $custom_limits = array();
        if (!empty($_POST['custom_limits']['episode_limit']) && $_POST['custom_limits']['episode_limit'] !== '') {
            $custom_limits['episode_limit'] = intval($_POST['custom_limits']['episode_limit']);
        }
        if (!empty($_POST['custom_limits']['widget_limit']) && $_POST['custom_limits']['widget_limit'] !== '') {
            $custom_limits['widget_limit'] = intval($_POST['custom_limits']['widget_limit']);
        }
        if (!empty($_POST['custom_limits']['ai_requests']) && $_POST['custom_limits']['ai_requests'] !== '') {
            $custom_limits['ai_requests'] = intval($_POST['custom_limits']['ai_requests']);
        }
        
        if (!empty($custom_limits)) {
            $membership_data['custom_limits'] = json_encode($custom_limits);
        } else {
            $membership_data['custom_limits'] = null;
        }
        
        // Update membership
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'spiralengine_memberships',
            $membership_data,
            array('user_id' => $user_id)
        );
        
        // Log the action
        SpiralEngine_Admin::log_admin_action('update_membership', 'user', $user_id, $membership_data);
        
        // Redirect with success message
        wp_redirect(add_query_arg(array(
            'page' => 'spiralengine-users',
            'action' => 'edit',
            'user_id' => $user_id,
            'message' => 'membership_updated'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Process admin notes update
     */
    private function process_update_notes() {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'spiralengine_user_notes_' . $user_id)) {
            wp_die(__('Security check failed.', 'spiralengine'));
        }
        
        $notes = wp_kses_post($_POST['admin_notes']);
        update_user_meta($user_id, 'spiralengine_admin_notes', $notes);
        
        // Log the action
        SpiralEngine_Admin::log_admin_action('update_user_notes', 'user', $user_id);
        
        // Redirect with success message
        wp_redirect(add_query_arg(array(
            'page' => 'spiralengine-users',
            'action' => 'edit',
            'user_id' => $user_id,
            'message' => 'notes_updated'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Process user creation
     */
    private function process_create_user() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'spiralengine_create_user')) {
            wp_die(__('Security check failed.', 'spiralengine'));
        }
        
        $user_data = array(
            'user_login' => sanitize_user($_POST['user_login']),
            'user_email' => sanitize_email($_POST['user_email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'user_pass' => wp_generate_password()
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            wp_die($user_id->get_error_message());
        }
        
        // Create membership
        SpiralEngine_Membership::create_membership($user_id, sanitize_key($_POST['membership_tier']));
        
        // Send welcome email if requested
        if (!empty($_POST['send_welcome'])) {
            wp_new_user_notification($user_id, null, 'both');
        }
        
        // Log the action
        SpiralEngine_Admin::log_admin_action('create_user', 'user', $user_id);
        
        // Redirect to edit page
        wp_redirect(add_query_arg(array(
            'page' => 'spiralengine-users',
            'action' => 'edit',
            'user_id' => $user_id,
            'message' => 'user_created'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Process user import
     */
    private function process_import_users() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'spiralengine_import_users')) {
            wp_die(__('Security check failed.', 'spiralengine'));
        }
        
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('File upload failed.', 'spiralengine'));
        }
        
        $file = $_FILES['import_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            wp_die(__('Could not open file.', 'spiralengine'));
        }
        
        $headers = fgetcsv($handle);
        $imported = 0;
        $skipped = 0;
        $errors = array();
        
        while (($data = fgetcsv($handle)) !== false) {
            $user_data = array_combine($headers, $data);
            
            // Check if user exists
            $exists = email_exists($user_data['email']);
            
            if ($exists && empty($_POST['update_existing'])) {
                $skipped++;
                continue;
            }
            
            if (!$exists) {
                // Create new user
                $user_id = wp_create_user(
                    $user_data['email'], // Use email as username
                    wp_generate_password(),
                    $user_data['email']
                );
                
                if (is_wp_error($user_id)) {
                    $errors[] = sprintf(__('Failed to create user %s: %s', 'spiralengine'), $user_data['email'], $user_id->get_error_message());
                    continue;
                }
            } else {
                $user_id = $exists;
            }
            
            // Update user data
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $user_data['first_name'] ?? '',
                'last_name' => $user_data['last_name'] ?? ''
            ));
            
            // Create/update membership
            $tier = !empty($user_data['tier']) ? $user_data['tier'] : $_POST['default_tier'];
            SpiralEngine_Membership::create_membership($user_id, $tier);
            
            // Send welcome email if requested and new user
            if (!$exists && !empty($_POST['send_welcome'])) {
                wp_new_user_notification($user_id, null, 'both');
            }
            
            $imported++;
        }
        
        fclose($handle);
        
        // Log the action
        SpiralEngine_Admin::log_admin_action('import_users', null, null, array(
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => count($errors)
        ));
        
        // Show results
        $message = sprintf(
            __('Import complete. %d users imported, %d skipped.', 'spiralengine'),
            $imported,
            $skipped
        );
        
        if (!empty($errors)) {
            $message .= ' ' . sprintf(__('%d errors occurred.', 'spiralengine'), count($errors));
        }
        
        wp_redirect(add_query_arg(array(
            'page' => 'spiralengine-users',
            'message' => urlencode($message)
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Process user impersonation
     */
    private function process_impersonate() {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'impersonate_user_' . $user_id)) {
            wp_die(__('Security check failed.', 'spiralengine'));
        }
        
        if (!current_user_can('spiralengine_impersonate_users')) {
            wp_die(__('You do not have permission to impersonate users.', 'spiralengine'));
        }
        
        // Store original user ID
        update_user_meta(get_current_user_id(), 'spiralengine_impersonating', $user_id);
        
        // Log the action
        SpiralEngine_Admin::log_admin_action('impersonate_user', 'user', $user_id);
        
        // Switch to the user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Redirect to dashboard
        wp_redirect(home_url('/spiralengine-dashboard/'));
        exit;
    }
    
    /**
     * Process delete user data
     */
    private function process_delete_data() {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_user_data_' . $user_id)) {
            wp_die(__('Security check failed.', 'spiralengine'));
        }
        
        if (!current_user_can('spiralengine_delete_user_data')) {
            wp_die(__('You do not have permission to delete user data.', 'spiralengine'));
        }
        
        global $wpdb;
        
        // Delete episodes
        $wpdb->delete(
            $wpdb->prefix . 'spiralengine_episodes',
            array('user_id' => $user_id),
            array('%d')
        );
        
        // Delete membership
        $wpdb->delete(
            $wpdb->prefix . 'spiralengine_memberships',
            array('user_id' => $user_id),
            array('%d')
        );
        
        // Delete user meta
        $wpdb->delete(
            $wpdb->usermeta,
            array(
                'user_id' => $user_id,
                'meta_key' => 'spiralengine_%'
            ),
            array('%d', '%s')
        );
        
        // Log the action
        SpiralEngine_Admin::log_admin_action('delete_user_data', 'user', $user_id);
        
        // Redirect with success message
        wp_redirect(add_query_arg(array(
            'page' => 'spiralengine-users',
            'message' => 'data_deleted'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * AJAX: Search users
     */
    public function ajax_user_search() {
        check_ajax_referer('spiralengine_user_management', 'nonce');
        
        if (!current_user_can('spiralengine_manage_users')) {
            wp_send_json_error();
        }
        
        $term = sanitize_text_field($_POST['term']);
        
        global $wpdb;
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.user_email, u.display_name, m.tier
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->prefix}spiralengine_memberships m ON u.ID = m.user_id
            WHERE u.user_email LIKE %s OR u.display_name LIKE %s
            LIMIT 10",
            '%' . $wpdb->esc_like($term) . '%',
            '%' . $wpdb->esc_like($term) . '%'
        ));
        
        $results = array();
        foreach ($users as $user) {
            $results[] = array(
                'label' => sprintf('%s (%s) - %s', $user->display_name, $user->user_email, ucfirst($user->tier)),
                'value' => $user->display_name,
                'url' => admin_url('admin.php?page=spiralengine-users&action=edit&user_id=' . $user->ID)
            );
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Update user tier
     */
    public function ajax_update_tier() {
        check_ajax_referer('spiralengine_user_management', 'nonce');
        
        if (!current_user_can('spiralengine_manage_users')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'spiralengine')));
        }
        
        $user_id = intval($_POST['user_id']);
        $tier = sanitize_key($_POST['tier']);
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'spiralengine_memberships',
            array('tier' => $tier),
            array('user_id' => $user_id)
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to update tier', 'spiralengine')));
        }
        
        // Log the action
        SpiralEngine_Admin::log_admin_action('quick_update_tier', 'user', $user_id, array('tier' => $tier));
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Get user details
     */
    public function ajax_get_user_details() {
        check_ajax_referer('spiralengine_user_management', 'nonce');
        
        if (!current_user_can('spiralengine_manage_users')) {
            wp_send_json_error();
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            wp_send_json_error();
        }
        
        $membership = SpiralEngine_Membership::get_user_membership($user_id);
        
        global $wpdb;
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_episodes,
                MAX(created_at) as last_episode,
                AVG(severity) as avg_severity,
                COUNT(DISTINCT widget_id) as widgets_used
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d",
            $user_id
        ));
        
        ob_start();
        ?>
        <div class="spiralengine-user-details">
            <div class="user-header">
                <?php echo get_avatar($user_id, 64); ?>
                <div>
                    <h3><?php echo esc_html($user->display_name); ?></h3>
                    <p><?php echo esc_html($user->user_email); ?></p>
                </div>
            </div>
            
            <table class="widefat">
                <tr>
                    <th><?php _e('Member Since', 'spiralengine'); ?></th>
                    <td><?php echo date_i18n(get_option('date_format'), strtotime($user->user_registered)); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Current Tier', 'spiralengine'); ?></th>
                    <td><span class="tier-badge tier-<?php echo esc_attr($membership->tier); ?>"><?php echo ucfirst($membership->tier); ?></span></td>
                </tr>
                <tr>
                    <th><?php _e('Status', 'spiralengine'); ?></th>
                    <td><?php echo ucfirst($membership->status); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Total Episodes', 'spiralengine'); ?></th>
                    <td><?php echo number_format($stats->total_episodes); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Widgets Used', 'spiralengine'); ?></th>
                    <td><?php echo number_format($stats->widgets_used); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Last Activity', 'spiralengine'); ?></th>
                    <td>
                        <?php 
                        echo $stats->last_episode 
                            ? human_time_diff(strtotime($stats->last_episode), current_time('timestamp')) . ' ' . __('ago', 'spiralengine')
                            : __('Never', 'spiralengine');
                        ?>
                    </td>
                </tr>
            </table>
            
            <div class="user-actions">
                <a href="<?php echo admin_url('admin.php?page=spiralengine-users&action=edit&user_id=' . $user_id); ?>" class="button button-primary">
                    <?php _e('Edit User', 'spiralengine'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=spiralengine-episodes&user_id=' . $user_id); ?>" class="button button-secondary">
                    <?php _e('View Episodes', 'spiralengine'); ?>
                </a>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'title' => sprintf(__('User Details: %s', 'spiralengine'), $user->display_name)
        ));
    }
    
    /**
     * AJAX: Impersonate user
     */
    public function ajax_impersonate_user() {
        check_ajax_referer('spiralengine_user_management', 'nonce');
        
        if (!current_user_can('spiralengine_impersonate_users')) {
            wp_send_json_error(array('message' => __('You do not have permission to impersonate users.', 'spiralengine')));
        }
        
        $user_id = intval($_POST['user_id']);
        
        // Store original user ID
        update_user_meta(get_current_user_id(), 'spiralengine_impersonating', $user_id);
        
        // Log the action
        SpiralEngine_Admin::log_admin_action('impersonate_user', 'user', $user_id);
        
        wp_send_json_success(array(
            'redirect_url' => wp_login_url() . '?spiralengine_impersonate=' . $user_id
        ));
    }
    
    /**
     * AJAX: Bulk user action
     */
    public function ajax_bulk_action() {
        check_ajax_referer('spiralengine_user_management', 'nonce');
        
        if (!current_user_can('spiralengine_manage_users')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'spiralengine')));
        }
        
        $action = sanitize_key($_POST['bulk_action']);
        $user_ids = array_map('intval', $_POST['user_ids']);
        
        if (empty($user_ids)) {
            wp_send_json_error(array('message' => __('No users selected', 'spiralengine')));
        }
        
        $result = false;
        
        switch ($action) {
            case 'change_tier':
                $new_tier = sanitize_key($_POST['new_tier']);
                $result = $this->bulk_change_tier($user_ids, $new_tier);
                break;
                
            case 'export':
                $result = $this->bulk_export_users($user_ids);
                break;
                
            case 'send_email':
                $result = $this->bulk_send_email($user_ids);
                break;
        }
        
        if ($result) {
            wp_send_json_success(array('message' => __('Bulk action completed successfully.', 'spiralengine')));
        } else {
            wp_send_json_error(array('message' => __('Bulk action failed.', 'spiralengine')));
        }
    }
    
    /**
     * Bulk change user tiers
     *
     * @param array $user_ids
     * @param string $new_tier
     * @return bool
     */
    private function bulk_change_tier($user_ids, $new_tier) {
        global $wpdb;
        
        $placeholders = array_fill(0, count($user_ids), '%d');
        $values = array_merge(array($new_tier), $user_ids);
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}spiralengine_memberships 
            SET tier = %s 
            WHERE user_id IN (" . implode(',', $placeholders) . ")",
            $values
        ));
        
        // Log the action
        SpiralEngine_Admin::log_admin_action('bulk_change_tier', null, null, array(
            'user_ids' => $user_ids,
            'new_tier' => $new_tier
        ));
        
        return $result !== false;
    }
    
    /**
     * Bulk export users
     *
     * @param array $user_ids
     * @return bool
     */
    private function bulk_export_users($user_ids) {
        // This would typically trigger a download
        // For AJAX, we'll just prepare the data
        
        $export_data = array();
        
        foreach ($user_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            $membership = SpiralEngine_Membership::get_user_membership($user_id);
            
            $export_data[] = array(
                'user_id' => $user_id,
                'email' => $user->user_email,
                'name' => $user->display_name,
                'tier' => $membership->tier,
                'status' => $membership->status,
                'registered' => $user->user_registered
            );
        }
        
        // Store in transient for download
        set_transient('spiralengine_export_' . get_current_user_id(), $export_data, HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Bulk send email to users
     *
     * @param array $user_ids
     * @return bool
     */
    private function bulk_send_email($user_ids) {
        // This would open an email composer
        // For now, we'll just return true
        return true;
    }
}

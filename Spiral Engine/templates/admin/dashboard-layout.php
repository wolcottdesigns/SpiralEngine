<?php
// templates/admin/dashboard-layout.php

/**
 * Spiral Engine Dashboard Layout Template
 * 
 * Main dashboard template with 12-column responsive grid
 * Based on Master Dashboard Command Center specifications
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard data
$dashboard_data = isset($args['data']) ? $args['data'] : array();
$modules = isset($args['modules']) ? $args['modules'] : array();
$user_preferences = get_user_meta(get_current_user_id(), 'spiral_engine_dashboard_preferences', true);
$dark_mode = !empty($user_preferences['dark_mode']);
?>

<div class="spiral-dashboard-container <?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    
    <!-- Emergency Alert Bar (if active) -->
    <?php if (get_option('spiral_engine_emergency_mode')): ?>
    <div class="emergency-alert-bar">
        <div class="emergency-content">
            <span class="emergency-icon">⚠️</span>
            <strong><?php _e('EMERGENCY MODE ACTIVE', 'spiral-engine'); ?></strong>
            <span class="emergency-details">
                <?php 
                $initiated_time = get_option('spiral_engine_emergency_initiated');
                $admin_id = get_option('spiral_engine_emergency_admin');
                $admin_user = get_user_by('id', $admin_id);
                
                echo sprintf(
                    __('Initiated by %s at %s', 'spiral-engine'),
                    $admin_user ? $admin_user->display_name : 'System',
                    date('g:i a', strtotime($initiated_time))
                );
                ?>
            </span>
            <?php if (current_user_can('manage_network')): ?>
            <button class="button button-small deactivate-emergency" id="deactivate-emergency">
                <?php _e('Deactivate', 'spiral-engine'); ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Dashboard Header -->
    <header class="spiral-dashboard-header">
        <div class="header-left">
            <h1 class="dashboard-title">
                <span class="spiral-logo"></span>
                <?php _e('Command Center', 'spiral-engine'); ?>
            </h1>
            <div class="dashboard-breadcrumb">
                <span class="breadcrumb-item"><?php _e('Spiral Engine', 'spiral-engine'); ?></span>
                <span class="breadcrumb-separator">›</span>
                <span class="breadcrumb-current"><?php _e('Dashboard', 'spiral-engine'); ?></span>
            </div>
        </div>
        
        <div class="header-center">
            <div class="global-search">
                <input type="text" 
                       id="global-command-search" 
                       placeholder="<?php esc_attr_e('Search or enter command...', 'spiral-engine'); ?>"
                       autocomplete="off">
                <div class="search-suggestions" id="search-suggestions"></div>
            </div>
        </div>
        
        <div class="header-right">
            <div class="header-notifications">
                <button class="notification-toggle" id="notification-toggle">
                    <span class="dashicons dashicons-bell"></span>
                    <span class="notification-count">3</span>
                </button>
                <div class="notification-dropdown" id="notification-dropdown">
                    <!-- Notifications loaded via AJAX -->
                </div>
            </div>
            
            <div class="header-user-menu">
                <?php echo get_avatar(get_current_user_id(), 32); ?>
                <div class="user-dropdown">
                    <a href="<?php echo admin_url('profile.php'); ?>"><?php _e('Profile', 'spiral-engine'); ?></a>
                    <a href="#" id="toggle-dark-mode"><?php _e('Dark Mode', 'spiral-engine'); ?></a>
                    <a href="#" id="dashboard-settings"><?php _e('Dashboard Settings', 'spiral-engine'); ?></a>
                </div>
            </div>
            
            <button class="dashboard-refresh" id="refresh-all-modules" title="<?php esc_attr_e('Refresh All', 'spiral-engine'); ?>">
                <span class="dashicons dashicons-update"></span>
            </button>
        </div>
    </header>
    
    <!-- Quick Stats Bar -->
    <div class="spiral-quick-stats">
        <div class="quick-stat">
            <span class="stat-icon active-users"></span>
            <div class="stat-content">
                <span class="stat-value" data-stat="active_users">—</span>
                <span class="stat-label"><?php _e('Active Now', 'spiral-engine'); ?></span>
            </div>
        </div>
        <div class="quick-stat">
            <span class="stat-icon episodes-today"></span>
            <div class="stat-content">
                <span class="stat-value" data-stat="episodes_today">—</span>
                <span class="stat-label"><?php _e('Episodes Today', 'spiral-engine'); ?></span>
            </div>
        </div>
        <div class="quick-stat">
            <span class="stat-icon correlations"></span>
            <div class="stat-content">
                <span class="stat-value" data-stat="correlations_found">—</span>
                <span class="stat-label"><?php _e('Patterns Found', 'spiral-engine'); ?></span>
            </div>
        </div>
        <div class="quick-stat">
            <span class="stat-icon system-health"></span>
            <div class="stat-content">
                <span class="stat-value" data-stat="system_health">—</span>
                <span class="stat-label"><?php _e('System Health', 'spiral-engine'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Main Dashboard Grid -->
    <div class="spiral-dashboard-main">
        <div class="dashboard-grid" id="dashboard-grid">
            
            <!-- Row 1: Critical Monitoring -->
            <div class="grid-row">
                <!-- System Health Monitor (6 columns) -->
                <div class="grid-col col-md-6" data-module="system_health">
                    <div class="dashboard-widget loading">
                        <div class="widget-header">
                            <h3><span class="dashicons dashicons-heart"></span> <?php _e('System Health Monitor', 'spiral-engine'); ?></h3>
                            <div class="widget-controls">
                                <button class="widget-refresh"><span class="dashicons dashicons-update"></span></button>
                                <button class="widget-settings"><span class="dashicons dashicons-admin-generic"></span></button>
                            </div>
                        </div>
                        <div class="widget-content" id="system-health-content">
                            <div class="loading-spinner">
                                <span class="spinner is-active"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Activity Command (6 columns) -->
                <div class="grid-col col-md-6" data-module="user_activity">
                    <div class="dashboard-widget loading">
                        <div class="widget-header">
                            <h3><span class="dashicons dashicons-groups"></span> <?php _e('User Activity Command', 'spiral-engine'); ?></h3>
                            <div class="widget-controls">
                                <button class="widget-refresh"><span class="dashicons dashicons-update"></span></button>
                                <button class="widget-fullscreen"><span class="dashicons dashicons-fullscreen-alt"></span></button>
                            </div>
                        </div>
                        <div class="widget-content" id="user-activity-content">
                            <div class="loading-spinner">
                                <span class="spinner is-active"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Row 2: Performance & Security -->
            <div class="grid-row">
                <!-- Widget Performance Center (4 columns) -->
                <div class="grid-col col-md-4" data-module="widget_performance">
                    <div class="dashboard-widget loading">
                        <div class="widget-header">
                            <h3><span class="dashicons dashicons-performance"></span> <?php _e('Widget Performance', 'spiral-engine'); ?></h3>
                            <div class="widget-controls">
                                <button class="widget-refresh"><span class="dashicons dashicons-update"></span></button>
                            </div>
                        </div>
                        <div class="widget-content" id="widget-performance-content">
                            <div class="loading-spinner">
                                <span class="spinner is-active"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Threat Matrix (4 columns) -->
                <div class="grid-col col-md-4" data-module="security_threats">
                    <div class="dashboard-widget loading">
                        <div class="widget-header">
                            <h3><span class="dashicons dashicons-shield"></span> <?php _e('Security Threats', 'spiral-engine'); ?></h3>
                            <div class="widget-controls">
                                <button class="widget-refresh"><span class="dashicons dashicons-update"></span></button>
                                <button class="widget-details"><span class="dashicons dashicons-info"></span></button>
                            </div>
                        </div>
                        <div class="widget-content" id="security-threats-content">
                            <div class="loading-spinner">
                                <span class="spinner is-active"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Revenue Intelligence Hub (4 columns) -->
                <div class="grid-col col-md-4" data-module="revenue_intelligence">
                    <div class="dashboard-widget loading">
                        <div class="widget-header">
                            <h3><span class="dashicons dashicons-chart-line"></span> <?php _e('Revenue Intelligence', 'spiral-engine'); ?></h3>
                            <div class="widget-controls">
                                <button class="widget-refresh"><span class="dashicons dashicons-update"></span></button>
                                <button class="widget-export"><span class="dashicons dashicons-download"></span></button>
                            </div>
                        </div>
                        <div class="widget-content" id="revenue-intelligence-content">
                            <div class="loading-spinner">
                                <span class="spinner is-active"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Row 3: AI Operations (Full Width) -->
            <div class="grid-row">
                <div class="grid-col col-md-12" data-module="ai_operations">
                    <div class="dashboard-widget loading">
                        <div class="widget-header">
                            <h3><span class="dashicons dashicons-admin-generic"></span> <?php _e('AI Operations Monitor', 'spiral-engine'); ?></h3>
                            <div class="widget-controls">
                                <button class="widget-refresh"><span class="dashicons dashicons-update"></span></button>
                                <button class="widget-configure"><span class="dashicons dashicons-admin-settings"></span></button>
                                <button class="widget-fullscreen"><span class="dashicons dashicons-fullscreen-alt"></span></button>
                            </div>
                        </div>
                        <div class="widget-content" id="ai-operations-content">
                            <div class="loading-spinner">
                                <span class="spinner is-active"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Row 4: Activity Feed & Quick Actions -->
            <div class="grid-row">
                <!-- Real-time Activity Feed (8 columns) -->
                <div class="grid-col col-md-8" data-module="activity_feed">
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><span class="dashicons dashicons-rss"></span> <?php _e('Real-time Activity Feed', 'spiral-engine'); ?></h3>
                            <div class="widget-controls">
                                <button class="widget-pause" id="pause-activity-feed">
                                    <span class="dashicons dashicons-controls-pause"></span>
                                </button>
                                <button class="widget-filter"><span class="dashicons dashicons-filter"></span></button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <div class="activity-feed-container" id="realtime-activity-feed">
                                <!-- Activity items loaded via WebSocket/AJAX -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions Panel (4 columns) -->
                <div class="grid-col col-md-4" data-module="quick_actions">
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><span class="dashicons dashicons-lightning"></span> <?php _e('Quick Actions', 'spiral-engine'); ?></h3>
                        </div>
                        <div class="widget-content">
                            <div class="quick-actions-grid">
                                <button class="quick-action-btn" data-action="deploy-widget">
                                    <span class="action-icon dashicons dashicons-plus-alt"></span>
                                    <span class="action-label"><?php _e('Deploy Widget', 'spiral-engine'); ?></span>
                                </button>
                                <button class="quick-action-btn" data-action="create-report">
                                    <span class="action-icon dashicons dashicons-chart-area"></span>
                                    <span class="action-label"><?php _e('Generate Report', 'spiral-engine'); ?></span>
                                </button>
                                <button class="quick-action-btn" data-action="analyze-patterns">
                                    <span class="action-icon dashicons dashicons-networking"></span>
                                    <span class="action-label"><?php _e('Analyze Patterns', 'spiral-engine'); ?></span>
                                </button>
                                <button class="quick-action-btn" data-action="system-backup">
                                    <span class="action-icon dashicons dashicons-backup"></span>
                                    <span class="action-label"><?php _e('System Backup', 'spiral-engine'); ?></span>
                                </button>
                                <button class="quick-action-btn" data-action="clear-cache">
                                    <span class="action-icon dashicons dashicons-trash"></span>
                                    <span class="action-label"><?php _e('Clear Cache', 'spiral-engine'); ?></span>
                                </button>
                                <button class="quick-action-btn" data-action="run-diagnostics">
                                    <span class="action-icon dashicons dashicons-admin-tools"></span>
                                    <span class="action-label"><?php _e('Diagnostics', 'spiral-engine'); ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Dashboard Settings Modal -->
    <div class="spiral-modal" id="dashboard-settings-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('Dashboard Settings', 'spiral-engine'); ?></h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="dashboard-settings-form">
                    <div class="settings-section">
                        <h3><?php _e('Display Preferences', 'spiral-engine'); ?></h3>
                        <label>
                            <input type="checkbox" name="dark_mode" <?php checked($dark_mode); ?>>
                            <?php _e('Enable Dark Mode', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="compact_view">
                            <?php _e('Compact View', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="auto_refresh" checked>
                            <?php _e('Auto-refresh widgets', 'spiral-engine'); ?>
                        </label>
                    </div>
                    
                    <div class="settings-section">
                        <h3><?php _e('Widget Layout', 'spiral-engine'); ?></h3>
                        <p><?php _e('Drag widgets to reorder them on your dashboard.', 'spiral-engine'); ?></p>
                        <button type="button" class="button" id="reset-widget-layout">
                            <?php _e('Reset to Default Layout', 'spiral-engine'); ?>
                        </button>
                    </div>
                    
                    <div class="settings-section">
                        <h3><?php _e('Notifications', 'spiral-engine'); ?></h3>
                        <label>
                            <input type="checkbox" name="critical_alerts" checked>
                            <?php _e('Critical System Alerts', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="pattern_alerts" checked>
                            <?php _e('New Pattern Discoveries', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="performance_alerts">
                            <?php _e('Performance Warnings', 'spiral-engine'); ?>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="button button-primary" id="save-dashboard-settings">
                    <?php _e('Save Settings', 'spiral-engine'); ?>
                </button>
                <button class="button modal-close">
                    <?php _e('Cancel', 'spiral-engine'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Fullscreen Widget Container -->
    <div class="spiral-fullscreen-widget" id="fullscreen-widget-container" style="display: none;">
        <div class="fullscreen-header">
            <h2 id="fullscreen-widget-title"></h2>
            <button class="close-fullscreen" id="close-fullscreen">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="fullscreen-content" id="fullscreen-widget-content">
            <!-- Fullscreen widget content -->
        </div>
    </div>
    
</div>

<script type="text/html" id="activity-feed-item-template">
    <div class="activity-item activity-{{ type }}">
        <span class="activity-icon">{{ icon }}</span>
        <div class="activity-content">
            <span class="activity-message">{{ message }}</span>
            <span class="activity-time">{{ time }}</span>
        </div>
    </div>
</script>

<script type="text/html" id="notification-item-template">
    <div class="notification-item notification-{{ level }}">
        <div class="notification-header">
            <span class="notification-title">{{ title }}</span>
            <span class="notification-time">{{ time }}</span>
        </div>
        <div class="notification-body">{{ message }}</div>
        <div class="notification-actions">
            <a href="{{ action_url }}" class="notification-action">{{ action_text }}</a>
            <button class="dismiss-notification" data-id="{{ id }}">Dismiss</button>
        </div>
    </div>
</script>

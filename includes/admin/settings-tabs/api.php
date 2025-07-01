<?php
/**
 * SpiralEngine Settings - API Tab
 * 
 * @package    SpiralEngine
 * @subpackage Includes/Admin/Settings
 * @file       includes/admin/settings-tabs/api.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$options = get_option('spiralengine_settings', array());
$api_settings = isset($options['api']) ? $options['api'] : array();

// Default values
$defaults = array(
    'enable_api' => false,
    'api_version' => 'v1',
    'require_authentication' => true,
    'rate_limiting' => true,
    'rate_limit_requests' => 1000,
    'rate_limit_window' => 3600,
    'allowed_origins' => '',
    'webhook_enabled' => false,
    'webhook_events' => array(),
    'api_logging' => false,
    'api_cache' => true,
    'cache_ttl' => 300,
    'jwt_secret' => wp_generate_password(64, true, true),
    'oauth_enabled' => false,
    'oauth_client_id' => '',
    'oauth_client_secret' => ''
);

$api_settings = wp_parse_args($api_settings, $defaults);

// Get API keys
$api_keys = spiralengine_get_api_keys();

// API statistics
$api_stats = spiralengine_get_api_stats();
?>

<div class="spiralengine-settings-panel" id="spiralengine-api">
    <h2><?php _e('API Settings', 'spiralengine'); ?></h2>
    <p class="description"><?php _e('Configure REST API access, webhooks, and integrations.', 'spiralengine'); ?></p>

    <!-- API Overview -->
    <div class="spiralengine-api-overview">
        <div class="spiralengine-info-box">
            <h4><?php _e('API Endpoint', 'spiralengine'); ?></h4>
            <code><?php echo home_url('/wp-json/spiralengine/' . $api_settings['api_version'] . '/'); ?></code>
            <p class="description"><?php _e('Base URL for all API requests.', 'spiralengine'); ?></p>
        </div>
        
        <div class="spiralengine-stats-row">
            <div class="spiralengine-stat-box">
                <span class="stat-value"><?php echo number_format($api_stats['total_requests']); ?></span>
                <span class="stat-label"><?php _e('Total Requests', 'spiralengine'); ?></span>
            </div>
            <div class="spiralengine-stat-box">
                <span class="stat-value"><?php echo number_format($api_stats['active_keys']); ?></span>
                <span class="stat-label"><?php _e('Active API Keys', 'spiralengine'); ?></span>
            </div>
            <div class="spiralengine-stat-box">
                <span class="stat-value"><?php echo number_format($api_stats['requests_today']); ?></span>
                <span class="stat-label"><?php _e('Requests Today', 'spiralengine'); ?></span>
            </div>
            <div class="spiralengine-stat-box">
                <span class="stat-value"><?php echo round($api_stats['avg_response_time'], 2); ?>ms</span>
                <span class="stat-label"><?php _e('Avg Response Time', 'spiralengine'); ?></span>
            </div>
        </div>
    </div>

    <!-- General API Settings -->
    <h3><?php _e('General Settings', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Enable API', 'spiralengine'); ?></th>
                <td>
                    <label for="enable_api">
                        <input type="checkbox" name="spiralengine_settings[api][enable_api]" id="enable_api" value="1" <?php checked($api_settings['enable_api']); ?>>
                        <?php _e('Enable REST API access', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Allow external applications to access SpiralEngine data via API.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="api-setting">
                <th scope="row"><label for="api_version"><?php _e('API Version', 'spiralengine'); ?></label></th>
                <td>
                    <select name="spiralengine_settings[api][api_version]" id="api_version" class="regular-text">
                        <option value="v1" <?php selected($api_settings['api_version'], 'v1'); ?>>v1 (Current)</option>
                        <option value="v2" <?php selected($api_settings['api_version'], 'v2'); ?>>v2 (Beta)</option>
                    </select>
                    <p class="description"><?php _e('Select the default API version for new integrations.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="api-setting">
                <th scope="row"><?php _e('Authentication', 'spiralengine'); ?></th>
                <td>
                    <label for="require_authentication">
                        <input type="checkbox" name="spiralengine_settings[api][require_authentication]" id="require_authentication" value="1" <?php checked($api_settings['require_authentication']); ?>>
                        <?php _e('Require authentication for all API requests', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Recommended for security. Disable only for public data endpoints.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="api-setting">
                <th scope="row"><?php _e('Rate Limiting', 'spiralengine'); ?></th>
                <td>
                    <label for="rate_limiting">
                        <input type="checkbox" name="spiralengine_settings[api][rate_limiting]" id="rate_limiting" value="1" <?php checked($api_settings['rate_limiting']); ?>>
                        <?php _e('Enable rate limiting', 'spiralengine'); ?>
                    </label>
                    
                    <div class="rate-limit-settings" style="margin-top: 10px; <?php echo !$api_settings['rate_limiting'] ? 'display:none;' : ''; ?>">
                        <label for="rate_limit_requests"><?php _e('Maximum requests:', 'spiralengine'); ?></label>
                        <input type="number" name="spiralengine_settings[api][rate_limit_requests]" id="rate_limit_requests" value="<?php echo esc_attr($api_settings['rate_limit_requests']); ?>" min="1" max="10000" class="small-text">
                        <?php _e('per', 'spiralengine'); ?>
                        <select name="spiralengine_settings[api][rate_limit_window]" id="rate_limit_window">
                            <option value="60" <?php selected($api_settings['rate_limit_window'], 60); ?>><?php _e('Minute', 'spiralengine'); ?></option>
                            <option value="3600" <?php selected($api_settings['rate_limit_window'], 3600); ?>><?php _e('Hour', 'spiralengine'); ?></option>
                            <option value="86400" <?php selected($api_settings['rate_limit_window'], 86400); ?>><?php _e('Day', 'spiralengine'); ?></option>
                        </select>
                    </div>
                </td>
            </tr>
            
            <tr class="api-setting">
                <th scope="row"><label for="allowed_origins"><?php _e('Allowed Origins (CORS)', 'spiralengine'); ?></label></th>
                <td>
                    <textarea name="spiralengine_settings[api][allowed_origins]" id="allowed_origins" rows="3" class="large-text"><?php echo esc_textarea($api_settings['allowed_origins']); ?></textarea>
                    <p class="description"><?php _e('Enter allowed origins for CORS, one per line. Use * to allow all origins (not recommended).', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="api-setting">
                <th scope="row"><?php _e('API Logging', 'spiralengine'); ?></th>
                <td>
                    <label for="api_logging">
                        <input type="checkbox" name="spiralengine_settings[api][api_logging]" id="api_logging" value="1" <?php checked($api_settings['api_logging']); ?>>
                        <?php _e('Log all API requests', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Useful for debugging but may impact performance.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="api-setting">
                <th scope="row"><?php _e('API Caching', 'spiralengine'); ?></th>
                <td>
                    <label for="api_cache">
                        <input type="checkbox" name="spiralengine_settings[api][api_cache]" id="api_cache" value="1" <?php checked($api_settings['api_cache']); ?>>
                        <?php _e('Enable API response caching', 'spiralengine'); ?>
                    </label>
                    
                    <div style="margin-top: 10px;">
                        <label for="cache_ttl"><?php _e('Cache TTL:', 'spiralengine'); ?></label>
                        <input type="number" name="spiralengine_settings[api][cache_ttl]" id="cache_ttl" value="<?php echo esc_attr($api_settings['cache_ttl']); ?>" min="0" max="3600" class="small-text">
                        <?php _e('seconds', 'spiralengine'); ?>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- API Keys Management -->
    <h3><?php _e('API Keys', 'spiralengine'); ?></h3>
    <p class="description"><?php _e('Manage API keys for external applications.', 'spiralengine'); ?></p>
    
    <div class="spiralengine-api-keys">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Name', 'spiralengine'); ?></th>
                    <th><?php _e('Key', 'spiralengine'); ?></th>
                    <th><?php _e('Permissions', 'spiralengine'); ?></th>
                    <th><?php _e('Created', 'spiralengine'); ?></th>
                    <th><?php _e('Last Used', 'spiralengine'); ?></th>
                    <th><?php _e('Actions', 'spiralengine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($api_keys)): ?>
                <tr>
                    <td colspan="6"><?php _e('No API keys created yet.', 'spiralengine'); ?></td>
                </tr>
                <?php else: ?>
                    <?php foreach ($api_keys as $key): ?>
                    <tr data-key-id="<?php echo esc_attr($key->id); ?>">
                        <td><?php echo esc_html($key->name); ?></td>
                        <td>
                            <code class="api-key-display"><?php echo esc_html(substr($key->api_key, 0, 10) . '...' . substr($key->api_key, -4)); ?></code>
                            <button type="button" class="button button-small reveal-key"><?php _e('Reveal', 'spiralengine'); ?></button>
                        </td>
                        <td><?php echo esc_html(implode(', ', $key->permissions)); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($key->created_at))); ?></td>
                        <td><?php echo $key->last_used ? esc_html(human_time_diff(strtotime($key->last_used), current_time('timestamp')) . ' ago') : __('Never', 'spiralengine'); ?></td>
                        <td>
                            <button type="button" class="button button-small edit-key"><?php _e('Edit', 'spiralengine'); ?></button>
                            <button type="button" class="button button-small button-link-delete revoke-key"><?php _e('Revoke', 'spiralengine'); ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <p>
            <button type="button" class="button button-primary" id="create-api-key"><?php _e('Create New API Key', 'spiralengine'); ?></button>
        </p>
    </div>

    <!-- Webhooks -->
    <h3><?php _e('Webhooks', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Enable Webhooks', 'spiralengine'); ?></th>
                <td>
                    <label for="webhook_enabled">
                        <input type="checkbox" name="spiralengine_settings[api][webhook_enabled]" id="webhook_enabled" value="1" <?php checked($api_settings['webhook_enabled']); ?>>
                        <?php _e('Send webhook notifications for events', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr class="webhook-setting" style="<?php echo !$api_settings['webhook_enabled'] ? 'display:none;' : ''; ?>">
                <th scope="row"><?php _e('Webhook Events', 'spiralengine'); ?></th>
                <td>
                    <fieldset>
                        <?php
                        $webhook_events = array(
                            'episode.created' => __('Episode Created', 'spiralengine'),
                            'episode.updated' => __('Episode Updated', 'spiralengine'),
                            'episode.deleted' => __('Episode Deleted', 'spiralengine'),
                            'goal.created' => __('Goal Created', 'spiralengine'),
                            'goal.completed' => __('Goal Completed', 'spiralengine'),
                            'user.registered' => __('User Registered', 'spiralengine'),
                            'subscription.created' => __('Subscription Created', 'spiralengine'),
                            'subscription.updated' => __('Subscription Updated', 'spiralengine'),
                            'subscription.cancelled' => __('Subscription Cancelled', 'spiralengine'),
                            'ai.analysis_complete' => __('AI Analysis Complete', 'spiralengine')
                        );
                        
                        foreach ($webhook_events as $event => $label):
                            $checked = in_array($event, $api_settings['webhook_events']);
                        ?>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[api][webhook_events][]" value="<?php echo esc_attr($event); ?>" <?php checked($checked); ?>>
                            <?php echo esc_html($label); ?>
                        </label><br>
                        <?php endforeach; ?>
                    </fieldset>
                </td>
            </tr>
        </tbody>
    </table>
    
    <!-- Webhook Endpoints -->
    <div class="webhook-setting" style="<?php echo !$api_settings['webhook_enabled'] ? 'display:none;' : ''; ?>">
        <h4><?php _e('Webhook Endpoints', 'spiralengine'); ?></h4>
        <?php
        $webhooks = spiralengine_get_webhooks();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('URL', 'spiralengine'); ?></th>
                    <th><?php _e('Events', 'spiralengine'); ?></th>
                    <th><?php _e('Status', 'spiralengine'); ?></th>
                    <th><?php _e('Actions', 'spiralengine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($webhooks)): ?>
                <tr>
                    <td colspan="4"><?php _e('No webhook endpoints configured.', 'spiralengine'); ?></td>
                </tr>
                <?php else: ?>
                    <?php foreach ($webhooks as $webhook): ?>
                    <tr data-webhook-id="<?php echo esc_attr($webhook->id); ?>">
                        <td><?php echo esc_html($webhook->url); ?></td>
                        <td><?php echo esc_html(implode(', ', $webhook->events)); ?></td>
                        <td>
                            <span class="webhook-status <?php echo $webhook->active ? 'active' : 'inactive'; ?>">
                                <?php echo $webhook->active ? __('Active', 'spiralengine') : __('Inactive', 'spiralengine'); ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="button button-small test-webhook"><?php _e('Test', 'spiralengine'); ?></button>
                            <button type="button" class="button button-small edit-webhook"><?php _e('Edit', 'spiralengine'); ?></button>
                            <button type="button" class="button button-small button-link-delete delete-webhook"><?php _e('Delete', 'spiralengine'); ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <p>
            <button type="button" class="button button-primary" id="add-webhook"><?php _e('Add Webhook Endpoint', 'spiralengine'); ?></button>
        </p>
    </div>

    <!-- OAuth Settings -->
    <h3><?php _e('OAuth 2.0', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('OAuth Authentication', 'spiralengine'); ?></th>
                <td>
                    <label for="oauth_enabled">
                        <input type="checkbox" name="spiralengine_settings[api][oauth_enabled]" id="oauth_enabled" value="1" <?php checked($api_settings['oauth_enabled']); ?>>
                        <?php _e('Enable OAuth 2.0 authentication', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Allow third-party apps to authenticate users via OAuth.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="oauth-setting" style="<?php echo !$api_settings['oauth_enabled'] ? 'display:none;' : ''; ?>">
                <th scope="row"><?php _e('OAuth Endpoints', 'spiralengine'); ?></th>
                <td>
                    <table class="form-table">
                        <tr>
                            <td><?php _e('Authorization:', 'spiralengine'); ?></td>
                            <td><code><?php echo home_url('/oauth/authorize'); ?></code></td>
                        </tr>
                        <tr>
                            <td><?php _e('Token:', 'spiralengine'); ?></td>
                            <td><code><?php echo home_url('/oauth/token'); ?></code></td>
                        </tr>
                        <tr>
                            <td><?php _e('Revoke:', 'spiralengine'); ?></td>
                            <td><code><?php echo home_url('/oauth/revoke'); ?></code></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- API Documentation -->
    <div class="spiralengine-api-docs">
        <h3><?php _e('API Documentation', 'spiralengine'); ?></h3>
        <p><?php _e('Complete API documentation is available for developers.', 'spiralengine'); ?></p>
        <p>
            <a href="<?php echo admin_url('admin.php?page=spiralengine-api-docs'); ?>" class="button button-secondary" target="_blank">
                <?php _e('View API Documentation', 'spiralengine'); ?>
            </a>
            <a href="<?php echo home_url('/wp-json/spiralengine/' . $api_settings['api_version'] . '/'); ?>" class="button button-secondary" target="_blank">
                <?php _e('API Explorer', 'spiralengine'); ?>
            </a>
        </p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Toggle API settings
        $('#enable_api').on('change', function() {
            $('.api-setting').toggle($(this).prop('checked'));
        }).trigger('change');
        
        // Toggle rate limit settings
        $('#rate_limiting').on('change', function() {
            $('.rate-limit-settings').toggle($(this).prop('checked'));
        });
        
        // Toggle webhook settings
        $('#webhook_enabled').on('change', function() {
            $('.webhook-setting').toggle($(this).prop('checked'));
        });
        
        // Toggle OAuth settings
        $('#oauth_enabled').on('change', function() {
            $('.oauth-setting').toggle($(this).prop('checked'));
        });
        
        // Create API key
        $('#create-api-key').on('click', function() {
            // Open modal for creating API key
            spiralengine_open_api_key_modal();
        });
        
        // Reveal API key
        $('.reveal-key').on('click', function() {
            var $button = $(this);
            var keyId = $button.closest('tr').data('key-id');
            
            $.post(ajaxurl, {
                action: 'spiralengine_reveal_api_key',
                key_id: keyId,
                nonce: '<?php echo wp_create_nonce('spiralengine_api_key'); ?>'
            }, function(response) {
                if (response.success) {
                    $button.siblings('.api-key-display').text(response.data.api_key);
                    $button.text('<?php echo esc_js(__('Hide', 'spiralengine')); ?>').removeClass('reveal-key').addClass('hide-key');
                }
            });
        });
        
        // Hide API key
        $(document).on('click', '.hide-key', function() {
            var $button = $(this);
            var key = $button.siblings('.api-key-display').text();
            var masked = key.substr(0, 10) + '...' + key.substr(-4);
            
            $button.siblings('.api-key-display').text(masked);
            $button.text('<?php echo esc_js(__('Reveal', 'spiralengine')); ?>').removeClass('hide-key').addClass('reveal-key');
        });
        
        // Revoke API key
        $('.revoke-key').on('click', function() {
            if (confirm('<?php echo esc_js(__('Are you sure you want to revoke this API key?', 'spiralengine')); ?>')) {
                var keyId = $(this).closest('tr').data('key-id');
                
                $.post(ajaxurl, {
                    action: 'spiralengine_revoke_api_key',
                    key_id: keyId,
                    nonce: '<?php echo wp_create_nonce('spiralengine_api_key'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        });
        
        // Add webhook
        $('#add-webhook').on('click', function() {
            spiralengine_open_webhook_modal();
        });
        
        // Test webhook
        $('.test-webhook').on('click', function() {
            var $button = $(this);
            var webhookId = $button.closest('tr').data('webhook-id');
            
            $button.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'spiralengine')); ?>');
            
            $.post(ajaxurl, {
                action: 'spiralengine_test_webhook',
                webhook_id: webhookId,
                nonce: '<?php echo wp_create_nonce('spiralengine_webhook'); ?>'
            }, function(response) {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Test', 'spiralengine')); ?>');
                
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert('<?php echo esc_js(__('Webhook test failed:', 'spiralengine')); ?> ' + response.data.message);
                }
            });
        });
        
        // Delete webhook
        $('.delete-webhook').on('click', function() {
            if (confirm('<?php echo esc_js(__('Are you sure you want to delete this webhook?', 'spiralengine')); ?>')) {
                var webhookId = $(this).closest('tr').data('webhook-id');
                
                $.post(ajaxurl, {
                    action: 'spiralengine_delete_webhook',
                    webhook_id: webhookId,
                    nonce: '<?php echo wp_create_nonce('spiralengine_webhook'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        });
    });
    
    // Helper functions for modals
    function spiralengine_open_api_key_modal() {
        // Implementation would open a modal for creating API keys
        alert('API key creation modal would open here');
    }
    
    function spiralengine_open_webhook_modal() {
        // Implementation would open a modal for adding webhooks
        alert('Webhook configuration modal would open here');
    }
    </script>
</div>

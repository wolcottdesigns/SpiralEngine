<?php
/**
 * SpiralEngine Settings - Advanced Tab
 * 
 * @package    SpiralEngine
 * @subpackage Includes/Admin/Settings
 * @file       includes/admin/settings-tabs/advanced.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$options = get_option('spiralengine_settings', array());
$advanced = isset($options['advanced']) ? $options['advanced'] : array();

// Default values
$defaults = array(
    // Performance
    'enable_object_cache' => true,
    'enable_page_cache' => false,
    'enable_cdn' => false,
    'cdn_url' => '',
    'lazy_load_widgets' => true,
    'minify_assets' => false,
    'combine_assets' => false,
    'async_processing' => true,
    'batch_size' => 100,
    
    // Database
    'cleanup_orphaned_data' => true,
    'optimize_tables' => false,
    'table_prefix' => 'spiralengine_',
    'use_custom_tables' => true,
    
    // Security
    'enable_brute_force_protection' => true,
    'max_login_attempts' => 5,
    'lockout_duration' => 900, // 15 minutes
    'enable_recaptcha' => false,
    'recaptcha_site_key' => '',
    'recaptcha_secret_key' => '',
    'force_ssl' => false,
    'disable_xmlrpc' => true,
    'hide_version' => true,
    
    // AI & Machine Learning
    'ai_provider' => 'openai',
    'openai_api_key' => '',
    'openai_model' => 'gpt-4',
    'ai_temperature' => 0.7,
    'ai_max_tokens' => 1000,
    'ai_cache_responses' => true,
    'ai_cache_ttl' => 86400, // 24 hours
    
    // Integration
    'google_analytics_4' => '',
    'facebook_pixel' => '',
    'hotjar_id' => '',
    'segment_write_key' => '',
    'custom_tracking_code' => '',
    
    // Developer
    'enable_dev_mode' => false,
    'show_query_monitor' => false,
    'enable_error_reporting' => false,
    'error_reporting_level' => E_ALL,
    'enable_rest_api_logging' => false,
    'enable_hooks_debugging' => false
);

$advanced = wp_parse_args($advanced, $defaults);

// Get performance metrics
$performance = spiralengine_get_performance_metrics();
?>

<div class="spiralengine-settings-panel" id="spiralengine-advanced">
    <h2><?php _e('Advanced Settings', 'spiralengine'); ?></h2>
    <p class="description"><?php _e('Configure advanced features, performance optimizations, and developer settings.', 'spiralengine'); ?></p>

    <!-- Performance -->
    <h3><?php _e('Performance Optimization', 'spiralengine'); ?></h3>
    
    <!-- Performance Metrics -->
    <div class="spiralengine-performance-metrics">
        <div class="metrics-grid">
            <div class="metric-box">
                <span class="metric-value"><?php echo round($performance['avg_load_time'], 2); ?>s</span>
                <span class="metric-label"><?php _e('Avg Page Load', 'spiralengine'); ?></span>
            </div>
            <div class="metric-box">
                <span class="metric-value"><?php echo round($performance['memory_usage'] / 1024 / 1024, 1); ?>MB</span>
                <span class="metric-label"><?php _e('Memory Usage', 'spiralengine'); ?></span>
            </div>
            <div class="metric-box">
                <span class="metric-value"><?php echo $performance['db_queries']; ?></span>
                <span class="metric-label"><?php _e('DB Queries', 'spiralengine'); ?></span>
            </div>
            <div class="metric-box">
                <span class="metric-value"><?php echo round($performance['cache_hit_rate'] * 100); ?>%</span>
                <span class="metric-label"><?php _e('Cache Hit Rate', 'spiralengine'); ?></span>
            </div>
        </div>
    </div>
    
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Object Caching', 'spiralengine'); ?></th>
                <td>
                    <label for="enable_object_cache">
                        <input type="checkbox" name="spiralengine_settings[advanced][enable_object_cache]" id="enable_object_cache" value="1" <?php checked($advanced['enable_object_cache']); ?>>
                        <?php _e('Enable object caching', 'spiralengine'); ?>
                    </label>
                    <p class="description">
                        <?php 
                        if (wp_using_ext_object_cache()) {
                            echo '<span style="color: green;">✓ ' . __('External object cache detected', 'spiralengine') . '</span>';
                        } else {
                            echo '<span style="color: orange;">⚠ ' . __('No external object cache detected. Consider using Redis or Memcached.', 'spiralengine') . '</span>';
                        }
                        ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Asset Optimization', 'spiralengine'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][lazy_load_widgets]" value="1" <?php checked($advanced['lazy_load_widgets']); ?>>
                            <?php _e('Lazy load widgets', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][minify_assets]" value="1" <?php checked($advanced['minify_assets']); ?>>
                            <?php _e('Minify CSS and JavaScript', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][combine_assets]" value="1" <?php checked($advanced['combine_assets']); ?>>
                            <?php _e('Combine CSS and JavaScript files', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('CDN Settings', 'spiralengine'); ?></th>
                <td>
                    <label for="enable_cdn">
                        <input type="checkbox" name="spiralengine_settings[advanced][enable_cdn]" id="enable_cdn" value="1" <?php checked($advanced['enable_cdn']); ?>>
                        <?php _e('Enable CDN for assets', 'spiralengine'); ?>
                    </label>
                    
                    <div class="cdn-settings" style="margin-top: 10px; <?php echo !$advanced['enable_cdn'] ? 'display:none;' : ''; ?>">
                        <label for="cdn_url"><?php _e('CDN URL:', 'spiralengine'); ?></label>
                        <input type="url" name="spiralengine_settings[advanced][cdn_url]" id="cdn_url" value="<?php echo esc_attr($advanced['cdn_url']); ?>" class="regular-text" placeholder="https://cdn.example.com">
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Async Processing', 'spiralengine'); ?></th>
                <td>
                    <label for="async_processing">
                        <input type="checkbox" name="spiralengine_settings[advanced][async_processing]" id="async_processing" value="1" <?php checked($advanced['async_processing']); ?>>
                        <?php _e('Process heavy tasks asynchronously', 'spiralengine'); ?>
                    </label>
                    
                    <div style="margin-top: 10px;">
                        <label for="batch_size"><?php _e('Batch size:', 'spiralengine'); ?></label>
                        <input type="number" name="spiralengine_settings[advanced][batch_size]" id="batch_size" value="<?php echo esc_attr($advanced['batch_size']); ?>" min="10" max="1000" class="small-text">
                        <?php _e('items per batch', 'spiralengine'); ?>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Database -->
    <h3><?php _e('Database Optimization', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Database Maintenance', 'spiralengine'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][cleanup_orphaned_data]" value="1" <?php checked($advanced['cleanup_orphaned_data']); ?>>
                            <?php _e('Automatically clean orphaned data', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][optimize_tables]" value="1" <?php checked($advanced['optimize_tables']); ?>>
                            <?php _e('Optimize database tables weekly', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                    
                    <p style="margin-top: 10px;">
                        <button type="button" class="button button-secondary" id="optimize-now"><?php _e('Optimize Now', 'spiralengine'); ?></button>
                        <span class="spinner"></span>
                        <span class="result-message"></span>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="table_prefix"><?php _e('Table Prefix', 'spiralengine'); ?></label></th>
                <td>
                    <input type="text" name="spiralengine_settings[advanced][table_prefix]" id="table_prefix" value="<?php echo esc_attr($advanced['table_prefix']); ?>" class="regular-text" readonly>
                    <p class="description"><?php _e('Database table prefix (cannot be changed after installation).', 'spiralengine'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Security -->
    <h3><?php _e('Security Settings', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Brute Force Protection', 'spiralengine'); ?></th>
                <td>
                    <label for="enable_brute_force_protection">
                        <input type="checkbox" name="spiralengine_settings[advanced][enable_brute_force_protection]" id="enable_brute_force_protection" value="1" <?php checked($advanced['enable_brute_force_protection']); ?>>
                        <?php _e('Enable brute force protection', 'spiralengine'); ?>
                    </label>
                    
                    <div class="brute-force-settings" style="margin-top: 10px; <?php echo !$advanced['enable_brute_force_protection'] ? 'display:none;' : ''; ?>">
                        <label><?php _e('Max login attempts:', 'spiralengine'); ?></label>
                        <input type="number" name="spiralengine_settings[advanced][max_login_attempts]" value="<?php echo esc_attr($advanced['max_login_attempts']); ?>" min="1" max="20" class="small-text">
                        
                        <label style="margin-left: 20px;"><?php _e('Lockout duration:', 'spiralengine'); ?></label>
                        <input type="number" name="spiralengine_settings[advanced][lockout_duration]" value="<?php echo esc_attr($advanced['lockout_duration']); ?>" min="60" max="86400" class="small-text">
                        <?php _e('seconds', 'spiralengine'); ?>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('reCAPTCHA', 'spiralengine'); ?></th>
                <td>
                    <label for="enable_recaptcha">
                        <input type="checkbox" name="spiralengine_settings[advanced][enable_recaptcha]" id="enable_recaptcha" value="1" <?php checked($advanced['enable_recaptcha']); ?>>
                        <?php _e('Enable Google reCAPTCHA', 'spiralengine'); ?>
                    </label>
                    
                    <div class="recaptcha-settings" style="margin-top: 10px; <?php echo !$advanced['enable_recaptcha'] ? 'display:none;' : ''; ?>">
                        <p>
                            <label for="recaptcha_site_key"><?php _e('Site Key:', 'spiralengine'); ?></label><br>
                            <input type="text" name="spiralengine_settings[advanced][recaptcha_site_key]" id="recaptcha_site_key" value="<?php echo esc_attr($advanced['recaptcha_site_key']); ?>" class="regular-text">
                        </p>
                        <p>
                            <label for="recaptcha_secret_key"><?php _e('Secret Key:', 'spiralengine'); ?></label><br>
                            <input type="password" name="spiralengine_settings[advanced][recaptcha_secret_key]" id="recaptcha_secret_key" value="<?php echo esc_attr($advanced['recaptcha_secret_key']); ?>" class="regular-text">
                        </p>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Additional Security', 'spiralengine'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][force_ssl]" value="1" <?php checked($advanced['force_ssl']); ?>>
                            <?php _e('Force SSL for all SpiralEngine pages', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][disable_xmlrpc]" value="1" <?php checked($advanced['disable_xmlrpc']); ?>>
                            <?php _e('Disable XML-RPC', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][hide_version]" value="1" <?php checked($advanced['hide_version']); ?>>
                            <?php _e('Hide SpiralEngine version from public', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- AI Settings -->
    <h3><?php _e('AI & Machine Learning', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><label for="ai_provider"><?php _e('AI Provider', 'spiralengine'); ?></label></th>
                <td>
                    <select name="spiralengine_settings[advanced][ai_provider]" id="ai_provider" class="regular-text">
                        <option value="openai" <?php selected($advanced['ai_provider'], 'openai'); ?>>OpenAI</option>
                        <option value="anthropic" <?php selected($advanced['ai_provider'], 'anthropic'); ?>>Anthropic Claude</option>
                        <option value="google" <?php selected($advanced['ai_provider'], 'google'); ?>>Google AI</option>
                        <option value="custom" <?php selected($advanced['ai_provider'], 'custom'); ?>>Custom Endpoint</option>
                    </select>
                </td>
            </tr>
            
            <tr class="ai-setting openai-setting" style="<?php echo $advanced['ai_provider'] !== 'openai' ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="openai_api_key"><?php _e('OpenAI API Key', 'spiralengine'); ?></label></th>
                <td>
                    <input type="password" name="spiralengine_settings[advanced][openai_api_key]" id="openai_api_key" value="<?php echo esc_attr($advanced['openai_api_key']); ?>" class="regular-text">
                    <p class="description"><?php _e('Your OpenAI API key for AI-powered features.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="ai-setting openai-setting" style="<?php echo $advanced['ai_provider'] !== 'openai' ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="openai_model"><?php _e('OpenAI Model', 'spiralengine'); ?></label></th>
                <td>
                    <select name="spiralengine_settings[advanced][openai_model]" id="openai_model" class="regular-text">
                        <option value="gpt-4" <?php selected($advanced['openai_model'], 'gpt-4'); ?>>GPT-4 (Best quality)</option>
                        <option value="gpt-4-turbo" <?php selected($advanced['openai_model'], 'gpt-4-turbo'); ?>>GPT-4 Turbo (Faster)</option>
                        <option value="gpt-3.5-turbo" <?php selected($advanced['openai_model'], 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Cost effective)</option>
                    </select>
                </td>
            </tr>
            
            <tr class="ai-setting">
                <th scope="row"><?php _e('AI Parameters', 'spiralengine'); ?></th>
                <td>
                    <label><?php _e('Temperature:', 'spiralengine'); ?></label>
                    <input type="number" name="spiralengine_settings[advanced][ai_temperature]" value="<?php echo esc_attr($advanced['ai_temperature']); ?>" min="0" max="2" step="0.1" class="small-text">
                    <span class="description"><?php _e('(0 = deterministic, 2 = creative)', 'spiralengine'); ?></span>
                    
                    <br style="margin-bottom: 10px;">
                    
                    <label><?php _e('Max tokens:', 'spiralengine'); ?></label>
                    <input type="number" name="spiralengine_settings[advanced][ai_max_tokens]" value="<?php echo esc_attr($advanced['ai_max_tokens']); ?>" min="100" max="4000" class="small-text">
                </td>
            </tr>
            
            <tr class="ai-setting">
                <th scope="row"><?php _e('AI Caching', 'spiralengine'); ?></th>
                <td>
                    <label for="ai_cache_responses">
                        <input type="checkbox" name="spiralengine_settings[advanced][ai_cache_responses]" id="ai_cache_responses" value="1" <?php checked($advanced['ai_cache_responses']); ?>>
                        <?php _e('Cache AI responses', 'spiralengine'); ?>
                    </label>
                    
                    <div style="margin-top: 10px;">
                        <label for="ai_cache_ttl"><?php _e('Cache duration:', 'spiralengine'); ?></label>
                        <input type="number" name="spiralengine_settings[advanced][ai_cache_ttl]" id="ai_cache_ttl" value="<?php echo esc_attr($advanced['ai_cache_ttl']); ?>" min="3600" max="2592000" class="small-text">
                        <?php _e('seconds', 'spiralengine'); ?>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Analytics Integration -->
    <h3><?php _e('Analytics & Tracking', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><label for="google_analytics_4"><?php _e('Google Analytics 4', 'spiralengine'); ?></label></th>
                <td>
                    <input type="text" name="spiralengine_settings[advanced][google_analytics_4]" id="google_analytics_4" value="<?php echo esc_attr($advanced['google_analytics_4']); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                    <p class="description"><?php _e('Your GA4 measurement ID.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="facebook_pixel"><?php _e('Facebook Pixel', 'spiralengine'); ?></label></th>
                <td>
                    <input type="text" name="spiralengine_settings[advanced][facebook_pixel]" id="facebook_pixel" value="<?php echo esc_attr($advanced['facebook_pixel']); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Facebook Pixel ID.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="custom_tracking_code"><?php _e('Custom Tracking Code', 'spiralengine'); ?></label></th>
                <td>
                    <textarea name="spiralengine_settings[advanced][custom_tracking_code]" id="custom_tracking_code" rows="5" class="large-text code"><?php echo esc_textarea($advanced['custom_tracking_code']); ?></textarea>
                    <p class="description"><?php _e('Additional tracking code to include in the header.', 'spiralengine'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Developer Options -->
    <h3><?php _e('Developer Options', 'spiralengine'); ?></h3>
    <div class="spiralengine-warning-box">
        <p><?php _e('⚠️ These options are for development only. Do not enable in production.', 'spiralengine'); ?></p>
    </div>
    
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Development Mode', 'spiralengine'); ?></th>
                <td>
                    <label for="enable_dev_mode">
                        <input type="checkbox" name="spiralengine_settings[advanced][enable_dev_mode]" id="enable_dev_mode" value="1" <?php checked($advanced['enable_dev_mode']); ?>>
                        <?php _e('Enable development mode', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Disables caching and enables verbose logging.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="dev-setting" style="<?php echo !$advanced['enable_dev_mode'] ? 'display:none;' : ''; ?>">
                <th scope="row"><?php _e('Debug Options', 'spiralengine'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][show_query_monitor]" value="1" <?php checked($advanced['show_query_monitor']); ?>>
                            <?php _e('Show database query monitor', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][enable_error_reporting]" value="1" <?php checked($advanced['enable_error_reporting']); ?>>
                            <?php _e('Enable PHP error reporting', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][enable_rest_api_logging]" value="1" <?php checked($advanced['enable_rest_api_logging']); ?>>
                            <?php _e('Log all REST API requests', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="spiralengine_settings[advanced][enable_hooks_debugging]" value="1" <?php checked($advanced['enable_hooks_debugging']); ?>>
                            <?php _e('Enable hooks debugging', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </tbody>
    </table>

    <script>
    jQuery(document).ready(function($) {
        // Toggle CDN settings
        $('#enable_cdn').on('change', function() {
            $('.cdn-settings').toggle($(this).prop('checked'));
        });
        
        // Toggle brute force settings
        $('#enable_brute_force_protection').on('change', function() {
            $('.brute-force-settings').toggle($(this).prop('checked'));
        });
        
        // Toggle reCAPTCHA settings
        $('#enable_recaptcha').on('change', function() {
            $('.recaptcha-settings').toggle($(this).prop('checked'));
        });
        
        // Toggle AI provider settings
        $('#ai_provider').on('change', function() {
            $('.ai-setting').hide();
            $('.' + $(this).val() + '-setting').show();
        });
        
        // Toggle developer settings
        $('#enable_dev_mode').on('change', function() {
            $('.dev-setting').toggle($(this).prop('checked'));
        });
        
        // Optimize database
        $('#optimize-now').on('click', function() {
            var $button = $(this);
            var $spinner = $button.siblings('.spinner');
            var $message = $button.siblings('.result-message');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('');
            
            $.post(ajaxurl, {
                action: 'spiralengine_optimize_database',
                nonce: '<?php echo wp_create_nonce('spiralengine_optimize'); ?>'
            }, function(response) {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
                
                if (response.success) {
                    $message.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    $message.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            });
        });
    });
    </script>
</div>


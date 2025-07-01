<?php
/**
 * SpiralEngine Settings - General Tab
 * 
 * @package    SpiralEngine
 * @subpackage Includes/Admin/Settings
 * @file       includes/admin/settings-tabs/general.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$options = get_option('spiralengine_settings', array());
$general = isset($options['general']) ? $options['general'] : array();

// Default values
$defaults = array(
    'enable_public_tracking' => false,
    'default_widget' => '',
    'tracking_interval' => 'none',
    'data_retention' => '0',
    'anonymous_mode' => false,
    'debug_mode' => false,
    'maintenance_mode' => false,
    'google_analytics_id' => '',
    'custom_css' => '',
    'time_zone' => wp_timezone_string()
);

$general = wp_parse_args($general, $defaults);
?>

<div class="spiralengine-settings-panel" id="spiralengine-general">
    <h2><?php _e('General Settings', 'spiralengine'); ?></h2>
    <p class="description"><?php _e('Configure the basic operation of SpiralEngine.', 'spiralengine'); ?></p>

    <table class="form-table" role="presentation">
        <tbody>
            <!-- Enable Public Tracking -->
            <tr>
                <th scope="row"><?php _e('Public Tracking', 'spiralengine'); ?></th>
                <td>
                    <label for="enable_public_tracking">
                        <input type="checkbox" name="spiralengine_settings[general][enable_public_tracking]" id="enable_public_tracking" value="1" <?php checked($general['enable_public_tracking']); ?>>
                        <?php _e('Allow non-logged-in users to track episodes', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Enable this to allow anonymous tracking without user registration.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Default Widget -->
            <tr>
                <th scope="row"><label for="default_widget"><?php _e('Default Widget', 'spiralengine'); ?></label></th>
                <td>
                    <select name="spiralengine_settings[general][default_widget]" id="default_widget" class="regular-text">
                        <option value=""><?php _e('None', 'spiralengine'); ?></option>
                        <?php
                        $widgets = spiralengine_get_available_widgets();
                        foreach ($widgets as $widget_id => $widget) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($widget_id),
                                selected($general['default_widget'], $widget_id, false),
                                esc_html($widget['name'])
                            );
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select the default widget for new users.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Tracking Reminder Interval -->
            <tr>
                <th scope="row"><label for="tracking_interval"><?php _e('Tracking Reminders', 'spiralengine'); ?></label></th>
                <td>
                    <select name="spiralengine_settings[general][tracking_interval]" id="tracking_interval" class="regular-text">
                        <option value="none" <?php selected($general['tracking_interval'], 'none'); ?>><?php _e('Disabled', 'spiralengine'); ?></option>
                        <option value="daily" <?php selected($general['tracking_interval'], 'daily'); ?>><?php _e('Daily', 'spiralengine'); ?></option>
                        <option value="weekly" <?php selected($general['tracking_interval'], 'weekly'); ?>><?php _e('Weekly', 'spiralengine'); ?></option>
                        <option value="custom" <?php selected($general['tracking_interval'], 'custom'); ?>><?php _e('Custom Schedule', 'spiralengine'); ?></option>
                    </select>
                    <p class="description"><?php _e('Send email reminders to users who haven\'t tracked recently.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Data Retention -->
            <tr>
                <th scope="row"><label for="data_retention"><?php _e('Data Retention', 'spiralengine'); ?></label></th>
                <td>
                    <select name="spiralengine_settings[general][data_retention]" id="data_retention" class="regular-text">
                        <option value="0" <?php selected($general['data_retention'], '0'); ?>><?php _e('Keep Forever', 'spiralengine'); ?></option>
                        <option value="30" <?php selected($general['data_retention'], '30'); ?>><?php _e('30 Days', 'spiralengine'); ?></option>
                        <option value="90" <?php selected($general['data_retention'], '90'); ?>><?php _e('90 Days', 'spiralengine'); ?></option>
                        <option value="180" <?php selected($general['data_retention'], '180'); ?>><?php _e('180 Days', 'spiralengine'); ?></option>
                        <option value="365" <?php selected($general['data_retention'], '365'); ?>><?php _e('1 Year', 'spiralengine'); ?></option>
                        <option value="730" <?php selected($general['data_retention'], '730'); ?>><?php _e('2 Years', 'spiralengine'); ?></option>
                    </select>
                    <p class="description"><?php _e('Automatically delete old episode data after this period. Use with caution.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Anonymous Mode -->
            <tr>
                <th scope="row"><?php _e('Anonymous Mode', 'spiralengine'); ?></th>
                <td>
                    <label for="anonymous_mode">
                        <input type="checkbox" name="spiralengine_settings[general][anonymous_mode]" id="anonymous_mode" value="1" <?php checked($general['anonymous_mode']); ?>>
                        <?php _e('Enable anonymous data collection', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Strip personally identifiable information from all tracking data.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Time Zone -->
            <tr>
                <th scope="row"><label for="time_zone"><?php _e('Time Zone', 'spiralengine'); ?></label></th>
                <td>
                    <select name="spiralengine_settings[general][time_zone]" id="time_zone" class="regular-text">
                        <?php echo wp_timezone_choice($general['time_zone']); ?>
                    </select>
                    <p class="description"><?php _e('Default timezone for episode timestamps and reports.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Google Analytics -->
            <tr>
                <th scope="row"><label for="google_analytics_id"><?php _e('Google Analytics ID', 'spiralengine'); ?></label></th>
                <td>
                    <input type="text" name="spiralengine_settings[general][google_analytics_id]" id="google_analytics_id" value="<?php echo esc_attr($general['google_analytics_id']); ?>" class="regular-text" placeholder="UA-XXXXXXXXX-X or G-XXXXXXXXXX">
                    <p class="description"><?php _e('Track widget usage with Google Analytics.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Debug Mode -->
            <tr>
                <th scope="row"><?php _e('Debug Mode', 'spiralengine'); ?></th>
                <td>
                    <label for="debug_mode">
                        <input type="checkbox" name="spiralengine_settings[general][debug_mode]" id="debug_mode" value="1" <?php checked($general['debug_mode']); ?>>
                        <?php _e('Enable debug logging', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Log debug information to help troubleshoot issues. Disable in production.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Maintenance Mode -->
            <tr>
                <th scope="row"><?php _e('Maintenance Mode', 'spiralengine'); ?></th>
                <td>
                    <label for="maintenance_mode">
                        <input type="checkbox" name="spiralengine_settings[general][maintenance_mode]" id="maintenance_mode" value="1" <?php checked($general['maintenance_mode']); ?>>
                        <?php _e('Enable maintenance mode', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Temporarily disable tracking while performing maintenance.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Custom CSS -->
            <tr>
                <th scope="row"><label for="custom_css"><?php _e('Custom CSS', 'spiralengine'); ?></label></th>
                <td>
                    <textarea name="spiralengine_settings[general][custom_css]" id="custom_css" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($general['custom_css']); ?></textarea>
                    <p class="description"><?php _e('Add custom CSS to style SpiralEngine elements.', 'spiralengine'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- System Information -->
    <div class="spiralengine-system-info">
        <h3><?php _e('System Information', 'spiralengine'); ?></h3>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <td><?php _e('WordPress Version', 'spiralengine'); ?></td>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('PHP Version', 'spiralengine'); ?></td>
                    <td><?php echo PHP_VERSION; ?></td>
                </tr>
                <tr>
                    <td><?php _e('MySQL Version', 'spiralengine'); ?></td>
                    <td><?php echo $GLOBALS['wpdb']->db_version(); ?></td>
                </tr>
                <tr>
                    <td><?php _e('SpiralEngine Version', 'spiralengine'); ?></td>
                    <td><?php echo SPIRALENGINE_VERSION; ?></td>
                </tr>
                <tr>
                    <td><?php _e('Active Theme', 'spiralengine'); ?></td>
                    <td><?php echo wp_get_theme()->get('Name'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Server Software', 'spiralengine'); ?></td>
                    <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                </tr>
                <tr>
                    <td><?php _e('Memory Limit', 'spiralengine'); ?></td>
                    <td><?php echo ini_get('memory_limit'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Max Upload Size', 'spiralengine'); ?></td>
                    <td><?php echo size_format(wp_max_upload_size()); ?></td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <button type="button" class="button button-secondary" id="spiralengine-export-system-info">
                <?php _e('Export System Info', 'spiralengine'); ?>
            </button>
        </p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Export system info
        $('#spiralengine-export-system-info').on('click', function() {
            window.location.href = ajaxurl + '?action=spiralengine_export_system_info&nonce=<?php echo wp_create_nonce('spiralengine_export'); ?>';
        });
        
        // Custom schedule options
        $('#tracking_interval').on('change', function() {
            if ($(this).val() === 'custom') {
                // Show custom schedule options (would need additional UI)
                alert('<?php echo esc_js(__('Custom scheduling will be available in a future update.', 'spiralengine')); ?>');
                $(this).val('none');
            }
        });
    });
    </script>
</div>


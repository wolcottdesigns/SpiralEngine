<?php
/**
 * SpiralEngine Settings - Notifications Tab
 * 
 * @package    SpiralEngine
 * @subpackage Includes/Admin/Settings
 * @file       includes/admin/settings-tabs/notifications.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$options = get_option('spiralengine_settings', array());
$notifications = isset($options['notifications']) ? $options['notifications'] : array();

// Default values
$defaults = array(
    'email_enabled' => true,
    'from_name' => get_bloginfo('name'),
    'from_email' => get_option('admin_email'),
    'reply_to' => get_option('admin_email'),
    'footer_text' => '',
    'email_template' => 'default',
    'email_logo' => '',
    'notifications' => array(
        'welcome_email' => true,
        'episode_reminder' => false,
        'goal_reminder' => true,
        'achievement_unlocked' => true,
        'weekly_summary' => true,
        'monthly_report' => false,
        'export_complete' => true,
        'subscription_renewal' => true,
        'subscription_failed' => true,
        'ai_insights_ready' => true,
        'account_inactive' => false,
        'data_export_request' => true
    ),
    'admin_notifications' => array(
        'new_user_signup' => true,
        'subscription_created' => true,
        'subscription_cancelled' => true,
        'payment_failed' => true,
        'high_usage_alert' => true,
        'error_threshold' => true,
        'daily_summary' => false
    ),
    'sms_enabled' => false,
    'sms_provider' => 'twilio',
    'twilio_sid' => '',
    'twilio_token' => '',
    'twilio_from' => '',
    'push_enabled' => false,
    'onesignal_app_id' => '',
    'onesignal_api_key' => ''
);

$notifications = wp_parse_args($notifications, $defaults);

// Get email templates
$email_templates = spiralengine_get_email_templates();
?>

<div class="spiralengine-settings-panel" id="spiralengine-notifications">
    <h2><?php _e('Notification Settings', 'spiralengine'); ?></h2>
    <p class="description"><?php _e('Configure email notifications, SMS alerts, and push notifications.', 'spiralengine'); ?></p>

    <!-- Email Settings -->
    <h3><?php _e('Email Settings', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Email Notifications', 'spiralengine'); ?></th>
                <td>
                    <label for="email_enabled">
                        <input type="checkbox" name="spiralengine_settings[notifications][email_enabled]" id="email_enabled" value="1" <?php checked($notifications['email_enabled']); ?>>
                        <?php _e('Enable email notifications', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            
            <tr class="email-setting">
                <th scope="row"><label for="from_name"><?php _e('From Name', 'spiralengine'); ?></label></th>
                <td>
                    <input type="text" name="spiralengine_settings[notifications][from_name]" id="from_name" value="<?php echo esc_attr($notifications['from_name']); ?>" class="regular-text">
                    <p class="description"><?php _e('The name that appears in the "From" field of emails.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="email-setting">
                <th scope="row"><label for="from_email"><?php _e('From Email', 'spiralengine'); ?></label></th>
                <td>
                    <input type="email" name="spiralengine_settings[notifications][from_email]" id="from_email" value="<?php echo esc_attr($notifications['from_email']); ?>" class="regular-text">
                    <p class="description"><?php _e('The email address used to send notifications.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="email-setting">
                <th scope="row"><label for="reply_to"><?php _e('Reply-To Email', 'spiralengine'); ?></label></th>
                <td>
                    <input type="email" name="spiralengine_settings[notifications][reply_to]" id="reply_to" value="<?php echo esc_attr($notifications['reply_to']); ?>" class="regular-text">
                    <p class="description"><?php _e('Where replies to notification emails should be sent.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="email-setting">
                <th scope="row"><label for="email_template"><?php _e('Email Template', 'spiralengine'); ?></label></th>
                <td>
                    <select name="spiralengine_settings[notifications][email_template]" id="email_template" class="regular-text">
                        <?php foreach ($email_templates as $template_id => $template_name): ?>
                        <option value="<?php echo esc_attr($template_id); ?>" <?php selected($notifications['email_template'], $template_id); ?>>
                            <?php echo esc_html($template_name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button button-secondary" id="preview-email-template"><?php _e('Preview', 'spiralengine'); ?></button>
                </td>
            </tr>
            
            <tr class="email-setting">
                <th scope="row"><label for="email_logo"><?php _e('Email Logo', 'spiralengine'); ?></label></th>
                <td>
                    <input type="hidden" name="spiralengine_settings[notifications][email_logo]" id="email_logo" value="<?php echo esc_attr($notifications['email_logo']); ?>">
                    <div id="email-logo-preview">
                        <?php if ($notifications['email_logo']): ?>
                        <img src="<?php echo esc_url($notifications['email_logo']); ?>" style="max-width: 200px; height: auto;">
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button button-secondary" id="upload-email-logo"><?php _e('Upload Logo', 'spiralengine'); ?></button>
                    <button type="button" class="button button-link" id="remove-email-logo" <?php echo !$notifications['email_logo'] ? 'style="display:none;"' : ''; ?>><?php _e('Remove', 'spiralengine'); ?></button>
                </td>
            </tr>
            
            <tr class="email-setting">
                <th scope="row"><label for="footer_text"><?php _e('Footer Text', 'spiralengine'); ?></label></th>
                <td>
                    <textarea name="spiralengine_settings[notifications][footer_text]" id="footer_text" rows="3" class="large-text"><?php echo esc_textarea($notifications['footer_text']); ?></textarea>
                    <p class="description"><?php _e('Additional text to include in email footers.', 'spiralengine'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- User Notifications -->
    <h3><?php _e('User Notifications', 'spiralengine'); ?></h3>
    <p class="description"><?php _e('Choose which notifications to send to users.', 'spiralengine'); ?></p>
    <table class="form-table spiralengine-notification-table" role="presentation">
        <tbody>
            <?php
            $user_notifications = array(
                'welcome_email' => __('Welcome Email', 'spiralengine'),
                'episode_reminder' => __('Episode Tracking Reminder', 'spiralengine'),
                'goal_reminder' => __('Goal Deadline Reminder', 'spiralengine'),
                'achievement_unlocked' => __('Achievement Unlocked', 'spiralengine'),
                'weekly_summary' => __('Weekly Summary Report', 'spiralengine'),
                'monthly_report' => __('Monthly Progress Report', 'spiralengine'),
                'export_complete' => __('Export Ready for Download', 'spiralengine'),
                'subscription_renewal' => __('Subscription Renewal Notice', 'spiralengine'),
                'subscription_failed' => __('Payment Failed', 'spiralengine'),
                'ai_insights_ready' => __('AI Insights Available', 'spiralengine'),
                'account_inactive' => __('Account Inactivity Warning', 'spiralengine'),
                'data_export_request' => __('Data Export Request Confirmation', 'spiralengine')
            );
            
            foreach ($user_notifications as $key => $label):
                $enabled = isset($notifications['notifications'][$key]) ? $notifications['notifications'][$key] : false;
            ?>
            <tr>
                <th scope="row"><?php echo esc_html($label); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="spiralengine_settings[notifications][notifications][<?php echo $key; ?>]" value="1" <?php checked($enabled); ?>>
                        <?php _e('Enabled', 'spiralengine'); ?>
                    </label>
                    <a href="#" class="customize-notification" data-notification="<?php echo esc_attr($key); ?>"><?php _e('Customize', 'spiralengine'); ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Admin Notifications -->
    <h3><?php _e('Admin Notifications', 'spiralengine'); ?></h3>
    <p class="description"><?php _e('Notifications sent to administrators.', 'spiralengine'); ?></p>
    <table class="form-table spiralengine-notification-table" role="presentation">
        <tbody>
            <?php
            $admin_notifications = array(
                'new_user_signup' => __('New User Registration', 'spiralengine'),
                'subscription_created' => __('New Subscription', 'spiralengine'),
                'subscription_cancelled' => __('Subscription Cancelled', 'spiralengine'),
                'payment_failed' => __('Payment Failed', 'spiralengine'),
                'high_usage_alert' => __('High Usage Alert', 'spiralengine'),
                'error_threshold' => __('Error Rate Threshold Exceeded', 'spiralengine'),
                'daily_summary' => __('Daily Admin Summary', 'spiralengine')
            );
            
            foreach ($admin_notifications as $key => $label):
                $enabled = isset($notifications['admin_notifications'][$key]) ? $notifications['admin_notifications'][$key] : false;
            ?>
            <tr>
                <th scope="row"><?php echo esc_html($label); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="spiralengine_settings[notifications][admin_notifications][<?php echo $key; ?>]" value="1" <?php checked($enabled); ?>>
                        <?php _e('Enabled', 'spiralengine'); ?>
                    </label>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- SMS Settings -->
    <h3><?php _e('SMS Notifications', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('SMS Notifications', 'spiralengine'); ?></th>
                <td>
                    <label for="sms_enabled">
                        <input type="checkbox" name="spiralengine_settings[notifications][sms_enabled]" id="sms_enabled" value="1" <?php checked($notifications['sms_enabled']); ?>>
                        <?php _e('Enable SMS notifications', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Requires Twilio account or other SMS provider.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="sms-setting" style="<?php echo !$notifications['sms_enabled'] ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="sms_provider"><?php _e('SMS Provider', 'spiralengine'); ?></label></th>
                <td>
                    <select name="spiralengine_settings[notifications][sms_provider]" id="sms_provider" class="regular-text">
                        <option value="twilio" <?php selected($notifications['sms_provider'], 'twilio'); ?>>Twilio</option>
                        <option value="nexmo" <?php selected($notifications['sms_provider'], 'nexmo'); ?>>Nexmo/Vonage</option>
                    </select>
                </td>
            </tr>
            
            <tr class="sms-setting twilio-setting" style="<?php echo !$notifications['sms_enabled'] || $notifications['sms_provider'] !== 'twilio' ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="twilio_sid"><?php _e('Twilio Account SID', 'spiralengine'); ?></label></th>
                <td>
                    <input type="text" name="spiralengine_settings[notifications][twilio_sid]" id="twilio_sid" value="<?php echo esc_attr($notifications['twilio_sid']); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr class="sms-setting twilio-setting" style="<?php echo !$notifications['sms_enabled'] || $notifications['sms_provider'] !== 'twilio' ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="twilio_token"><?php _e('Twilio Auth Token', 'spiralengine'); ?></label></th>
                <td>
                    <input type="password" name="spiralengine_settings[notifications][twilio_token]" id="twilio_token" value="<?php echo esc_attr($notifications['twilio_token']); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr class="sms-setting twilio-setting" style="<?php echo !$notifications['sms_enabled'] || $notifications['sms_provider'] !== 'twilio' ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="twilio_from"><?php _e('From Number', 'spiralengine'); ?></label></th>
                <td>
                    <input type="text" name="spiralengine_settings[notifications][twilio_from]" id="twilio_from" value="<?php echo esc_attr($notifications['twilio_from']); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Twilio phone number.', 'spiralengine'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Push Notifications -->
    <h3><?php _e('Push Notifications', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Push Notifications', 'spiralengine'); ?></th>
                <td>
                    <label for="push_enabled">
                        <input type="checkbox" name="spiralengine_settings[notifications][push_enabled]" id="push_enabled" value="1" <?php checked($notifications['push_enabled']); ?>>
                        <?php _e('Enable push notifications', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Requires OneSignal account or other push service.', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="push-setting" style="<?php echo !$notifications['push_enabled'] ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="onesignal_app_id"><?php _e('OneSignal App ID', 'spiralengine'); ?></label></th>
                <td>
                    <input type="text" name="spiralengine_settings[notifications][onesignal_app_id]" id="onesignal_app_id" value="<?php echo esc_attr($notifications['onesignal_app_id']); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr class="push-setting" style="<?php echo !$notifications['push_enabled'] ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="onesignal_api_key"><?php _e('OneSignal REST API Key', 'spiralengine'); ?></label></th>
                <td>
                    <input type="password" name="spiralengine_settings[notifications][onesignal_api_key]" id="onesignal_api_key" value="<?php echo esc_attr($notifications['onesignal_api_key']); ?>" class="regular-text">
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Test Notifications -->
    <div class="spiralengine-test-notifications">
        <h3><?php _e('Test Notifications', 'spiralengine'); ?></h3>
        <p><?php _e('Send a test notification to verify your settings.', 'spiralengine'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="test_email"><?php _e('Test Email', 'spiralengine'); ?></label></th>
                <td>
                    <input type="email" id="test_email" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" class="regular-text">
                    <button type="button" class="button button-secondary" id="send-test-email"><?php _e('Send Test Email', 'spiralengine'); ?></button>
                    <span class="spinner"></span>
                    <span class="test-result"></span>
                </td>
            </tr>
            
            <?php if ($notifications['sms_enabled']): ?>
            <tr>
                <th scope="row"><label for="test_phone"><?php _e('Test Phone', 'spiralengine'); ?></label></th>
                <td>
                    <input type="tel" id="test_phone" class="regular-text" placeholder="+1234567890">
                    <button type="button" class="button button-secondary" id="send-test-sms"><?php _e('Send Test SMS', 'spiralengine'); ?></button>
                    <span class="spinner"></span>
                    <span class="test-result"></span>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Toggle email settings
        $('#email_enabled').on('change', function() {
            $('.email-setting').toggle($(this).prop('checked'));
        }).trigger('change');
        
        // Toggle SMS settings
        $('#sms_enabled').on('change', function() {
            $('.sms-setting').toggle($(this).prop('checked'));
            if ($(this).prop('checked')) {
                $('#sms_provider').trigger('change');
            }
        });
        
        // Toggle provider-specific settings
        $('#sms_provider').on('change', function() {
            $('.twilio-setting').toggle($(this).val() === 'twilio');
            $('.nexmo-setting').toggle($(this).val() === 'nexmo');
        });
        
        // Toggle push settings
        $('#push_enabled').on('change', function() {
            $('.push-setting').toggle($(this).prop('checked'));
        });
        
        // Upload email logo
        $('#upload-email-logo').on('click', function(e) {
            e.preventDefault();
            
            var file_frame = wp.media({
                title: '<?php echo esc_js(__('Select Email Logo', 'spiralengine')); ?>',
                button: {
                    text: '<?php echo esc_js(__('Use this image', 'spiralengine')); ?>'
                },
                multiple: false
            });
            
            file_frame.on('select', function() {
                var attachment = file_frame.state().get('selection').first().toJSON();
                $('#email_logo').val(attachment.url);
                $('#email-logo-preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto;">');
                $('#remove-email-logo').show();
            });
            
            file_frame.open();
        });
        
        // Remove email logo
        $('#remove-email-logo').on('click', function(e) {
            e.preventDefault();
            $('#email_logo').val('');
            $('#email-logo-preview').empty();
            $(this).hide();
        });
        
        // Preview email template
        $('#preview-email-template').on('click', function() {
            var template = $('#email_template').val();
            window.open(ajaxurl + '?action=spiralengine_preview_email_template&template=' + template + '&nonce=<?php echo wp_create_nonce('spiralengine_preview'); ?>', 'preview', 'width=600,height=700');
        });
        
        // Send test email
        $('#send-test-email').on('click', function() {
            var $button = $(this);
            var $spinner = $button.siblings('.spinner');
            var $result = $button.siblings('.test-result');
            var email = $('#test_email').val();
            
            if (!email) {
                alert('<?php echo esc_js(__('Please enter an email address', 'spiralengine')); ?>');
                return;
            }
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.text('');
            
            $.post(ajaxurl, {
                action: 'spiralengine_send_test_email',
                email: email,
                nonce: '<?php echo wp_create_nonce('spiralengine_test_notification'); ?>'
            }, function(response) {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
                
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            });
        });
        
        // Send test SMS
        $('#send-test-sms').on('click', function() {
            var $button = $(this);
            var $spinner = $button.siblings('.spinner');
            var $result = $button.siblings('.test-result');
            var phone = $('#test_phone').val();
            
            if (!phone) {
                alert('<?php echo esc_js(__('Please enter a phone number', 'spiralengine')); ?>');
                return;
            }
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.text('');
            
            $.post(ajaxurl, {
                action: 'spiralengine_send_test_sms',
                phone: phone,
                nonce: '<?php echo wp_create_nonce('spiralengine_test_notification'); ?>'
            }, function(response) {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
                
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            });
        });
        
        // Customize notification
        $('.customize-notification').on('click', function(e) {
            e.preventDefault();
            var notification = $(this).data('notification');
            window.open(ajaxurl + '?action=spiralengine_customize_notification&notification=' + notification + '&nonce=<?php echo wp_create_nonce('spiralengine_customize'); ?>', 'customize', 'width=800,height=600');
        });
    });
    </script>
</div>

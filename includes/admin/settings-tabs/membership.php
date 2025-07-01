<?php
/**
 * SpiralEngine Settings - Membership Tab
 * 
 * @package    SpiralEngine
 * @subpackage Includes/Admin/Settings
 * @file       includes/admin/settings-tabs/membership.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$options = get_option('spiralengine_settings', array());
$membership = isset($options['membership']) ? $options['membership'] : array();

// Default values
$defaults = array(
    'enable_memberships' => true,
    'free_tier_enabled' => true,
    'free_tier_limits' => array(
        'episodes_per_month' => 50,
        'widgets' => 1,
        'history_days' => 30
    ),
    'trial_enabled' => true,
    'trial_days' => 14,
    'stripe_mode' => 'test',
    'stripe_test_publishable' => '',
    'stripe_test_secret' => '',
    'stripe_live_publishable' => '',
    'stripe_live_secret' => '',
    'stripe_webhook_secret' => '',
    'currency' => 'USD',
    'tax_enabled' => false,
    'tax_rate' => '',
    'price_display' => 'excluding_tax'
);

$membership = wp_parse_args($membership, $defaults);

// Get membership statistics
$stats = spiralengine_get_membership_stats();
?>

<div class="spiralengine-settings-panel" id="spiralengine-membership">
    <h2><?php _e('Membership Settings', 'spiralengine'); ?></h2>
    <p class="description"><?php _e('Configure membership tiers, pricing, and payment processing.', 'spiralengine'); ?></p>

    <!-- Membership Stats -->
    <div class="spiralengine-stats-box">
        <h3><?php _e('Membership Overview', 'spiralengine'); ?></h3>
        <div class="spiralengine-stats-grid">
            <div class="spiralengine-stat">
                <span class="spiralengine-stat-value"><?php echo number_format($stats['total_users']); ?></span>
                <span class="spiralengine-stat-label"><?php _e('Total Users', 'spiralengine'); ?></span>
            </div>
            <div class="spiralengine-stat">
                <span class="spiralengine-stat-value"><?php echo number_format($stats['free_users']); ?></span>
                <span class="spiralengine-stat-label"><?php _e('Free Users', 'spiralengine'); ?></span>
            </div>
            <div class="spiralengine-stat">
                <span class="spiralengine-stat-value"><?php echo number_format($stats['paid_users']); ?></span>
                <span class="spiralengine-stat-label"><?php _e('Paid Users', 'spiralengine'); ?></span>
            </div>
            <div class="spiralengine-stat">
                <span class="spiralengine-stat-value">$<?php echo number_format($stats['mrr'], 2); ?></span>
                <span class="spiralengine-stat-label"><?php _e('Monthly Revenue', 'spiralengine'); ?></span>
            </div>
        </div>
    </div>

    <table class="form-table" role="presentation">
        <tbody>
            <!-- Enable Memberships -->
            <tr>
                <th scope="row"><?php _e('Membership System', 'spiralengine'); ?></th>
                <td>
                    <label for="enable_memberships">
                        <input type="checkbox" name="spiralengine_settings[membership][enable_memberships]" id="enable_memberships" value="1" <?php checked($membership['enable_memberships']); ?>>
                        <?php _e('Enable membership system', 'spiralengine'); ?>
                    </label>
                    <p class="description"><?php _e('Turn on paid memberships and tier restrictions.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Free Tier Settings -->
            <tr class="membership-setting">
                <th scope="row"><?php _e('Free Tier', 'spiralengine'); ?></th>
                <td>
                    <fieldset>
                        <label for="free_tier_enabled">
                            <input type="checkbox" name="spiralengine_settings[membership][free_tier_enabled]" id="free_tier_enabled" value="1" <?php checked($membership['free_tier_enabled']); ?>>
                            <?php _e('Enable free tier', 'spiralengine'); ?>
                        </label>
                        
                        <div class="free-tier-limits" style="margin-top: 15px;">
                            <label><?php _e('Free Tier Limits:', 'spiralengine'); ?></label>
                            
                            <div style="margin: 10px 0;">
                                <label for="free_episodes_limit"><?php _e('Episodes per month:', 'spiralengine'); ?></label>
                                <input type="number" name="spiralengine_settings[membership][free_tier_limits][episodes_per_month]" id="free_episodes_limit" value="<?php echo esc_attr($membership['free_tier_limits']['episodes_per_month']); ?>" min="0" max="1000" class="small-text">
                            </div>
                            
                            <div style="margin: 10px 0;">
                                <label for="free_widgets_limit"><?php _e('Active widgets:', 'spiralengine'); ?></label>
                                <input type="number" name="spiralengine_settings[membership][free_tier_limits][widgets]" id="free_widgets_limit" value="<?php echo esc_attr($membership['free_tier_limits']['widgets']); ?>" min="1" max="10" class="small-text">
                            </div>
                            
                            <div style="margin: 10px 0;">
                                <label for="free_history_limit"><?php _e('History retention (days):', 'spiralengine'); ?></label>
                                <input type="number" name="spiralengine_settings[membership][free_tier_limits][history_days]" id="free_history_limit" value="<?php echo esc_attr($membership['free_tier_limits']['history_days']); ?>" min="0" max="365" class="small-text">
                            </div>
                        </div>
                    </fieldset>
                </td>
            </tr>

            <!-- Trial Settings -->
            <tr class="membership-setting">
                <th scope="row"><?php _e('Free Trial', 'spiralengine'); ?></th>
                <td>
                    <label for="trial_enabled">
                        <input type="checkbox" name="spiralengine_settings[membership][trial_enabled]" id="trial_enabled" value="1" <?php checked($membership['trial_enabled']); ?>>
                        <?php _e('Enable free trial for paid tiers', 'spiralengine'); ?>
                    </label>
                    
                    <div style="margin-top: 10px;">
                        <label for="trial_days"><?php _e('Trial duration:', 'spiralengine'); ?></label>
                        <input type="number" name="spiralengine_settings[membership][trial_days]" id="trial_days" value="<?php echo esc_attr($membership['trial_days']); ?>" min="1" max="90" class="small-text">
                        <?php _e('days', 'spiralengine'); ?>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Tier Pricing -->
    <h3><?php _e('Tier Pricing', 'spiralengine'); ?></h3>
    <table class="widefat striped spiralengine-tier-pricing">
        <thead>
            <tr>
                <th><?php _e('Tier', 'spiralengine'); ?></th>
                <th><?php _e('Monthly Price', 'spiralengine'); ?></th>
                <th><?php _e('Annual Price', 'spiralengine'); ?></th>
                <th><?php _e('Features', 'spiralengine'); ?></th>
                <th><?php _e('Active Users', 'spiralengine'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $tiers = array(
                'silver' => array(
                    'name' => __('Silver', 'spiralengine'),
                    'monthly_price' => 9.99,
                    'annual_price' => 99,
                    'features' => __('Unlimited tracking, All widgets, Goals', 'spiralengine')
                ),
                'gold' => array(
                    'name' => __('Gold', 'spiralengine'),
                    'monthly_price' => 19.99,
                    'annual_price' => 199,
                    'features' => __('Silver + AI insights, API access', 'spiralengine')
                ),
                'platinum' => array(
                    'name' => __('Platinum', 'spiralengine'),
                    'monthly_price' => 39.99,
                    'annual_price' => 399,
                    'features' => __('Gold + Custom AI, Priority support', 'spiralengine')
                )
            );
            
            foreach ($tiers as $tier_key => $tier_data):
                $monthly_field = "tier_{$tier_key}_monthly";
                $annual_field = "tier_{$tier_key}_annual";
                $monthly_price = isset($membership[$monthly_field]) ? $membership[$monthly_field] : $tier_data['monthly_price'];
                $annual_price = isset($membership[$annual_field]) ? $membership[$annual_field] : $tier_data['annual_price'];
            ?>
            <tr>
                <td><strong><?php echo esc_html($tier_data['name']); ?></strong></td>
                <td>
                    <span class="spiralengine-currency"><?php echo spiralengine_get_currency_symbol($membership['currency']); ?></span>
                    <input type="number" name="spiralengine_settings[membership][<?php echo $monthly_field; ?>]" value="<?php echo esc_attr($monthly_price); ?>" min="0" step="0.01" class="small-text">
                </td>
                <td>
                    <span class="spiralengine-currency"><?php echo spiralengine_get_currency_symbol($membership['currency']); ?></span>
                    <input type="number" name="spiralengine_settings[membership][<?php echo $annual_field; ?>]" value="<?php echo esc_attr($annual_price); ?>" min="0" step="0.01" class="small-text">
                </td>
                <td><?php echo esc_html($tier_data['features']); ?></td>
                <td><?php echo number_format($stats['users_by_tier'][$tier_key] ?? 0); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Payment Settings -->
    <h3><?php _e('Payment Settings', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <!-- Stripe Mode -->
            <tr>
                <th scope="row"><?php _e('Stripe Mode', 'spiralengine'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="spiralengine_settings[membership][stripe_mode]" value="test" <?php checked($membership['stripe_mode'], 'test'); ?>>
                            <?php _e('Test Mode', 'spiralengine'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="spiralengine_settings[membership][stripe_mode]" value="live" <?php checked($membership['stripe_mode'], 'live'); ?>>
                            <?php _e('Live Mode', 'spiralengine'); ?>
                        </label>
                    </fieldset>
                    <p class="description"><?php _e('Use test mode for development and testing.', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Test Keys -->
            <tr class="stripe-test-keys">
                <th scope="row"><label for="stripe_test_publishable"><?php _e('Test Publishable Key', 'spiralengine'); ?></label></th>
                <td>
                    <input type="text" name="spiralengine_settings[membership][stripe_test_publishable]" id="stripe_test_publishable" value="<?php echo esc_attr($membership['stripe_test_publishable']); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Stripe test publishable key (starts with pk_test_).', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="stripe-test-keys">
                <th scope="row"><label for="stripe_test_secret"><?php _e('Test Secret Key', 'spiralengine'); ?></label></th>
                <td>
                    <input type="password" name="spiralengine_settings[membership][stripe_test_secret]" id="stripe_test_secret" value="<?php echo esc_attr($membership['stripe_test_secret']); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Stripe test secret key (starts with sk_test_).', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Live Keys -->
            <tr class="stripe-live-keys" style="<?php echo $membership['stripe_mode'] === 'test' ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="stripe_live_publishable"><?php _e('Live Publishable Key', 'spiralengine'); ?></label></th>
                <td>
                    <input type="text" name="spiralengine_settings[membership][stripe_live_publishable]" id="stripe_live_publishable" value="<?php echo esc_attr($membership['stripe_live_publishable']); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Stripe live publishable key (starts with pk_live_).', 'spiralengine'); ?></p>
                </td>
            </tr>
            
            <tr class="stripe-live-keys" style="<?php echo $membership['stripe_mode'] === 'test' ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="stripe_live_secret"><?php _e('Live Secret Key', 'spiralengine'); ?></label></th>
                <td>
                    <input type="password" name="spiralengine_settings[membership][stripe_live_secret]" id="stripe_live_secret" value="<?php echo esc_attr($membership['stripe_live_secret']); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Stripe live secret key (starts with sk_live_).', 'spiralengine'); ?></p>
                </td>
            </tr>

            <!-- Webhook Secret -->
            <tr>
                <th scope="row"><label for="stripe_webhook_secret"><?php _e('Webhook Secret', 'spiralengine'); ?></label></th>
                <td>
                    <input type="password" name="spiralengine_settings[membership][stripe_webhook_secret]" id="stripe_webhook_secret" value="<?php echo esc_attr($membership['stripe_webhook_secret']); ?>" class="regular-text">
                    <p class="description">
                        <?php _e('Your Stripe webhook endpoint signing secret.', 'spiralengine'); ?><br>
                        <?php _e('Webhook URL:', 'spiralengine'); ?> <code><?php echo home_url('/wp-json/spiralengine/v1/stripe-webhook'); ?></code>
                    </p>
                </td>
            </tr>

            <!-- Currency -->
            <tr>
                <th scope="row"><label for="currency"><?php _e('Currency', 'spiralengine'); ?></label></th>
                <td>
                    <select name="spiralengine_settings[membership][currency]" id="currency" class="regular-text">
                        <?php
                        $currencies = array(
                            'USD' => __('US Dollar ($)', 'spiralengine'),
                            'EUR' => __('Euro (€)', 'spiralengine'),
                            'GBP' => __('British Pound (£)', 'spiralengine'),
                            'CAD' => __('Canadian Dollar ($)', 'spiralengine'),
                            'AUD' => __('Australian Dollar ($)', 'spiralengine'),
                            'JPY' => __('Japanese Yen (¥)', 'spiralengine'),
                            'CHF' => __('Swiss Franc (CHF)', 'spiralengine'),
                            'NZD' => __('New Zealand Dollar ($)', 'spiralengine')
                        );
                        
                        foreach ($currencies as $code => $name) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($code),
                                selected($membership['currency'], $code, false),
                                esc_html($name)
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>

            <!-- Tax Settings -->
            <tr>
                <th scope="row"><?php _e('Tax Settings', 'spiralengine'); ?></th>
                <td>
                    <label for="tax_enabled">
                        <input type="checkbox" name="spiralengine_settings[membership][tax_enabled]" id="tax_enabled" value="1" <?php checked($membership['tax_enabled']); ?>>
                        <?php _e('Enable tax calculation', 'spiralengine'); ?>
                    </label>
                    
                    <div class="tax-settings" style="margin-top: 10px; <?php echo !$membership['tax_enabled'] ? 'display:none;' : ''; ?>">
                        <label for="tax_rate"><?php _e('Default tax rate (%):', 'spiralengine'); ?></label>
                        <input type="number" name="spiralengine_settings[membership][tax_rate]" id="tax_rate" value="<?php echo esc_attr($membership['tax_rate']); ?>" min="0" max="100" step="0.01" class="small-text">
                        
                        <div style="margin-top: 10px;">
                            <label for="price_display"><?php _e('Display prices:', 'spiralengine'); ?></label>
                            <select name="spiralengine_settings[membership][price_display]" id="price_display">
                                <option value="excluding_tax" <?php selected($membership['price_display'], 'excluding_tax'); ?>><?php _e('Excluding tax', 'spiralengine'); ?></option>
                                <option value="including_tax" <?php selected($membership['price_display'], 'including_tax'); ?>><?php _e('Including tax', 'spiralengine'); ?></option>
                            </select>
                        </div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Test Connection -->
    <div class="spiralengine-test-connection">
        <h3><?php _e('Test Connection', 'spiralengine'); ?></h3>
        <p><?php _e('Test your Stripe connection to ensure everything is configured correctly.', 'spiralengine'); ?></p>
        <p>
            <button type="button" class="button button-secondary" id="test-stripe-connection">
                <?php _e('Test Stripe Connection', 'spiralengine'); ?>
            </button>
            <span class="spinner" style="float: none;"></span>
            <span class="test-result"></span>
        </p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Toggle Stripe key fields based on mode
        $('input[name="spiralengine_settings[membership][stripe_mode]"]').on('change', function() {
            if ($(this).val() === 'test') {
                $('.stripe-test-keys').show();
                $('.stripe-live-keys').hide();
            } else {
                $('.stripe-test-keys').hide();
                $('.stripe-live-keys').show();
            }
        });
        
        // Toggle membership settings
        $('#enable_memberships').on('change', function() {
            $('.membership-setting').toggle($(this).prop('checked'));
        }).trigger('change');
        
        // Toggle free tier limits
        $('#free_tier_enabled').on('change', function() {
            $('.free-tier-limits').toggle($(this).prop('checked'));
        }).trigger('change');
        
        // Toggle tax settings
        $('#tax_enabled').on('change', function() {
            $('.tax-settings').toggle($(this).prop('checked'));
        });
        
        // Test Stripe connection
        $('#test-stripe-connection').on('click', function() {
            const $button = $(this);
            const $spinner = $button.siblings('.spinner');
            const $result = $button.siblings('.test-result');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.text('');
            
            $.post(ajaxurl, {
                action: 'spiralengine_test_stripe_connection',
                nonce: '<?php echo wp_create_nonce('spiralengine_test_stripe'); ?>'
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
        
        // Update currency symbol
        $('#currency').on('change', function() {
            const symbols = {
                'USD': '$',
                'EUR': '€',
                'GBP': '£',
                'CAD': '$',
                'AUD': '$',
                'JPY': '¥',
                'CHF': 'CHF',
                'NZD': '$'
            };
            
            $('.spiralengine-currency').text(symbols[$(this).val()] || '$');
        });
    });
    </script>
</div>


<?php
// templates/email/welcome-email.php

/**
 * SPIRAL Engine Welcome Email Template
 * 
 * Creates welcome email from Member Signup specifications:
 * - Personalized content
 * - Getting started guide
 * - Privacy information
 * - Support resources
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Email variables should be passed: $user, $site_name, $login_url, etc.
$display_name = isset($user) ? $user->display_name : __('Member', 'spiral-engine');
$site_name = get_bloginfo('name');
$site_url = home_url();
$login_url = wp_login_url();
$dashboard_url = home_url('/member-dashboard/');
$assessment_url = home_url('/daily-assessment/');
$privacy_url = home_url('/privacy-policy/');
$support_url = home_url('/support/');

// Get membership info if available
$membership_level = 'Discovery';
if (isset($user) && function_exists('mepr_get_user_active_memberships')) {
    $memberships = mepr_get_user_active_memberships($user->ID);
    if (!empty($memberships)) {
        $product = new MeprProduct($memberships[0]->product_id);
        $membership_level = $product->post_title;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php printf(__('Welcome to %s', 'spiral-engine'), $site_name); ?></title>
    <style>
        /* Reset styles */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; }
        
        /* Remove default styling */
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
        
        /* Mobile styles */
        @media screen and (max-width: 600px) {
            .mobile-hide { display: none !important; }
            .mobile-center { text-align: center !important; }
            .container { padding: 0 !important; width: 100% !important; }
            .content { padding: 10px !important; }
            .button { width: 100% !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #333333; background-color: #f4f4f4;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table class="container" border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 40px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: bold;">
                                <?php _e('Welcome to Your Wellness Journey', 'spiral-engine'); ?>
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 18px; opacity: 0.9;">
                                <?php printf(__('Hi %s, we\'re excited to have you aboard!', 'spiral-engine'), $display_name); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td class="content" style="padding: 40px 30px;">
                            
                            <!-- Journey Begins -->
                            <div style="text-align: center; margin-bottom: 40px;">
                                <img src="<?php echo SPIRALENGINE_URL; ?>assets/images/compass-icon.png" alt="Compass" style="width: 80px; height: 80px; margin-bottom: 20px;">
                                <h2 style="color: #333333; font-size: 24px; margin: 0 0 10px 0;">
                                    <?php _e('Your Journey Begins Now', 'spiral-engine'); ?>
                                </h2>
                                <p style="color: #666666; font-size: 16px; margin: 0;">
                                    <?php _e('You\'ve taken the first step towards better mental wellness. You\'ve earned your first Journey Marker: <strong>First Steps</strong> (+500 Compass Points!)', 'spiral-engine'); ?>
                                </p>
                            </div>
                            
                            <!-- Getting Started -->
                            <div style="background-color: #f8f9fa; border-radius: 8px; padding: 30px; margin-bottom: 30px;">
                                <h3 style="color: #333333; font-size: 20px; margin: 0 0 20px 0;">
                                    <?php _e('Getting Started is Easy', 'spiral-engine'); ?>
                                </h3>
                                
                                <ol style="margin: 0; padding-left: 20px; color: #666666;">
                                    <li style="margin-bottom: 15px;">
                                        <strong><?php _e('Take Your First Assessment', 'spiral-engine'); ?></strong><br>
                                        <?php _e('Complete a quick 6-question SPIRAL assessment to establish your baseline. It takes less than 2 minutes!', 'spiral-engine'); ?>
                                    </li>
                                    <li style="margin-bottom: 15px;">
                                        <strong><?php _e('Explore Your Dashboard', 'spiral-engine'); ?></strong><br>
                                        <?php _e('View your progress, track patterns, and discover insights about your wellness journey.', 'spiral-engine'); ?>
                                    </li>
                                    <li style="margin-bottom: 15px;">
                                        <strong><?php _e('Make it a Daily Habit', 'spiral-engine'); ?></strong><br>
                                        <?php _e('Check in daily to track your progress and earn more Journey Markers along the way.', 'spiral-engine'); ?>
                                    </li>
                                </ol>
                                
                                <div style="text-align: center; margin-top: 30px;">
                                    <a href="<?php echo esc_url($assessment_url); ?>" style="display: inline-block; padding: 15px 40px; background-color: #4CAF50; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                                        <?php _e('Take Your First Assessment', 'spiral-engine'); ?>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Membership Info -->
                            <div style="border: 2px solid #e0e0e0; border-radius: 8px; padding: 25px; margin-bottom: 30px;">
                                <h3 style="color: #333333; font-size: 20px; margin: 0 0 15px 0;">
                                    <?php _e('Your Membership', 'spiral-engine'); ?>
                                </h3>
                                <p style="margin: 0 0 10px 0; color: #666666;">
                                    <strong><?php _e('Level:', 'spiral-engine'); ?></strong> <?php echo esc_html($membership_level); ?>
                                </p>
                                
                                <?php if ($membership_level === 'Discovery') : ?>
                                <p style="margin: 0; color: #666666; font-size: 14px;">
                                    <?php _e('You\'re starting with our free Discovery membership. You have full access to assessments, basic insights, and can earn all Journey Markers!', 'spiral-engine'); ?>
                                </p>
                                <?php else : ?>
                                <p style="margin: 0; color: #666666; font-size: 14px;">
                                    <?php printf(__('Thank you for being a %s member! You have access to all premium features.', 'spiral-engine'), $membership_level); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Privacy & Support -->
                            <div style="background-color: #e3f2fd; border-radius: 8px; padding: 25px; margin-bottom: 30px;">
                                <h3 style="color: #333333; font-size: 20px; margin: 0 0 15px 0;">
                                    <?php _e('Your Privacy Matters', 'spiral-engine'); ?>
                                </h3>
                                <p style="margin: 0 0 15px 0; color: #666666;">
                                    <?php _e('We take your privacy seriously. Your wellness data is encrypted and never shared without your explicit consent.', 'spiral-engine'); ?>
                                </p>
                                <ul style="margin: 0; padding-left: 20px; color: #666666;">
                                    <li><?php _e('You control your data', 'spiral-engine'); ?></li>
                                    <li><?php _e('Export or delete anytime', 'spiral-engine'); ?></li>
                                    <li><?php _e('GDPR compliant', 'spiral-engine'); ?></li>
                                    <li><?php _e('No data selling, ever', 'spiral-engine'); ?></li>
                                </ul>
                                <p style="margin: 15px 0 0 0;">
                                    <a href="<?php echo esc_url($privacy_url); ?>" style="color: #2196F3; text-decoration: none; font-weight: bold;">
                                        <?php _e('Read Our Privacy Policy →', 'spiral-engine'); ?>
                                    </a>
                                </p>
                            </div>
                            
                            <!-- Support Resources -->
                            <div style="text-align: center; margin-bottom: 30px;">
                                <h3 style="color: #333333; font-size: 20px; margin: 0 0 15px 0;">
                                    <?php _e('We\'re Here to Help', 'spiral-engine'); ?>
                                </h3>
                                <p style="color: #666666; margin: 0 0 20px 0;">
                                    <?php _e('Have questions? Need support? We\'re always here for you.', 'spiral-engine'); ?>
                                </p>
                                
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td align="center" style="padding: 10px;">
                                            <a href="<?php echo esc_url($support_url); ?>" style="display: inline-block; padding: 12px 30px; background-color: #f5f5f5; color: #333333; text-decoration: none; border-radius: 5px; border: 1px solid #e0e0e0;">
                                                <?php _e('Visit Support Center', 'spiral-engine'); ?>
                                            </a>
                                        </td>
                                        <td align="center" style="padding: 10px;">
                                            <a href="<?php echo esc_url($dashboard_url); ?>" style="display: inline-block; padding: 12px 30px; background-color: #f5f5f5; color: #333333; text-decoration: none; border-radius: 5px; border: 1px solid #e0e0e0;">
                                                <?php _e('Go to Dashboard', 'spiral-engine'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Quick Tips -->
                            <div style="border-top: 2px solid #e0e0e0; padding-top: 30px; margin-top: 40px;">
                                <h3 style="color: #333333; font-size: 18px; margin: 0 0 20px 0;">
                                    <?php _e('Quick Tips for Success', 'spiral-engine'); ?>
                                </h3>
                                <ul style="margin: 0; padding-left: 20px; color: #666666;">
                                    <li style="margin-bottom: 10px;">
                                        <strong><?php _e('Be Honest:', 'spiral-engine'); ?></strong> 
                                        <?php _e('The assessments work best when you answer truthfully', 'spiral-engine'); ?>
                                    </li>
                                    <li style="margin-bottom: 10px;">
                                        <strong><?php _e('Stay Consistent:', 'spiral-engine'); ?></strong> 
                                        <?php _e('Daily check-ins help you spot patterns', 'spiral-engine'); ?>
                                    </li>
                                    <li style="margin-bottom: 10px;">
                                        <strong><?php _e('Celebrate Progress:', 'spiral-engine'); ?></strong> 
                                        <?php _e('Every Journey Marker is an achievement', 'spiral-engine'); ?>
                                    </li>
                                    <li style="margin-bottom: 10px;">
                                        <strong><?php _e('Reach Out:', 'spiral-engine'); ?></strong> 
                                        <?php _e('We\'re here if you need support', 'spiral-engine'); ?>
                                    </li>
                                </ul>
                            </div>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-radius: 0 0 8px 8px;">
                            <p style="margin: 0 0 10px 0; color: #666666; font-size: 14px;">
                                <?php _e('Welcome aboard! We\'re honored to be part of your wellness journey.', 'spiral-engine'); ?>
                            </p>
                            <p style="margin: 0 0 20px 0; color: #666666; font-size: 14px;">
                                <strong><?php echo esc_html($site_name); ?></strong>
                            </p>
                            
                            <div style="margin-bottom: 20px;">
                                <a href="<?php echo esc_url($site_url); ?>" style="color: #2196F3; text-decoration: none; margin: 0 10px;">
                                    <?php _e('Website', 'spiral-engine'); ?>
                                </a>
                                <span style="color: #cccccc;">|</span>
                                <a href="<?php echo esc_url($privacy_url); ?>" style="color: #2196F3; text-decoration: none; margin: 0 10px;">
                                    <?php _e('Privacy', 'spiral-engine'); ?>
                                </a>
                                <span style="color: #cccccc;">|</span>
                                <a href="<?php echo esc_url($support_url); ?>" style="color: #2196F3; text-decoration: none; margin: 0 10px;">
                                    <?php _e('Support', 'spiral-engine'); ?>
                                </a>
                            </div>
                            
                            <p style="margin: 0; color: #999999; font-size: 12px;">
                                <?php _e('You received this email because you registered for an account. If you didn\'t sign up, please ignore this email.', 'spiral-engine'); ?>
                            </p>
                            
                            <!-- Unsubscribe link -->
                            <p style="margin: 20px 0 0 0; color: #999999; font-size: 12px;">
                                <a href="<?php echo esc_url(add_query_arg('action', 'unsubscribe', home_url('/email-preferences/'))); ?>" style="color: #999999; text-decoration: underline;">
                                    <?php _e('Manage email preferences', 'spiral-engine'); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>


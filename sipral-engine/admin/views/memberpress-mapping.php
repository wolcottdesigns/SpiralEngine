<?php
/**
 * MemberPress Mapping Admin View
 * 
 * @package    SPIRAL_Engine
 * @subpackage Admin/Views
 * @file       admin/views/memberpress-mapping.php
 * @since      1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current mappings
$current_mappings = $this->tier_mappings;
$tiers = $this->membership_tiers;
?>

<div class="wrap spiralengine-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('MemberPress Integration', 'spiralengine'); ?>
    </h1>
    
    <?php if (!$this->memberpress_active): ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('MemberPress is not active. Please install and activate MemberPress to use this integration.', 'spiralengine'); ?>
            </p>
        </div>
    <?php else: ?>
        
        <div class="spiralengine-memberpress-mapping">
            <div class="sp-mapping-intro">
                <p>
                    <?php esc_html_e('Map your MemberPress membership levels to SPIRAL Engine tiers. Members will automatically receive access to features based on their subscription.', 'spiralengine'); ?>
                </p>
            </div>
            
            <form id="spiralengine-tier-mappings" method="post">
                <?php wp_nonce_field('spiralengine_memberpress_nonce', 'spiralengine_nonce'); ?>
                
                <div class="sp-tier-mapping-grid">
                    <?php foreach ($tiers as $tier_key => $tier_info): ?>
                        <div class="sp-tier-mapping-card" style="border-top: 4px solid <?php echo esc_attr($tier_info['color']); ?>">
                            <div class="sp-tier-header">
                                <span class="dashicons <?php echo esc_attr($tier_info['icon']); ?>"></span>
                                <h3><?php echo esc_html($tier_info['name']); ?></h3>
                                <span class="sp-tier-level">Level <?php echo esc_html($tier_info['level']); ?></span>
                            </div>
                            
                            <p class="sp-tier-description">
                                <?php echo esc_html($tier_info['description']); ?>
                            </p>
                            
                            <div class="sp-membership-selector">
                                <label>
                                    <?php esc_html_e('Select MemberPress Memberships:', 'spiralengine'); ?>
                                </label>
                                
                                <div class="sp-membership-list">
                                    <?php if (!empty($memberships)): ?>
                                        <?php foreach ($memberships as $membership): ?>
                                            <?php 
                                            $is_mapped = isset($current_mappings[$tier_key]) && 
                                                       in_array($membership['id'], $current_mappings[$tier_key]);
                                            ?>
                                            <label class="sp-membership-option">
                                                <input type="checkbox" 
                                                       name="mappings[<?php echo esc_attr($tier_key); ?>][]" 
                                                       value="<?php echo esc_attr($membership['id']); ?>"
                                                       <?php checked($is_mapped); ?>>
                                                <span class="sp-membership-name">
                                                    <?php echo esc_html($membership['title']); ?>
                                                </span>
                                                <span class="sp-membership-meta">
                                                    <?php 
                                                    if ($membership['price'] > 0) {
                                                        echo '$' . number_format($membership['price'], 2);
                                                        if ($membership['period'] > 0) {
                                                            echo ' / ' . $membership['period'] . ' ' . $membership['period_type'];
                                                        }
                                                    } else {
                                                        echo 'Free';
                                                    }
                                                    ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="sp-no-memberships">
                                            <?php esc_html_e('No MemberPress memberships found. Please create memberships first.', 'spiralengine'); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($tier_key !== 'discovery'): ?>
                                <div class="sp-tier-features">
                                    <h4><?php esc_html_e('Included Features:', 'spiralengine'); ?></h4>
                                    <?php 
                                    $access_control = SpiralEngine_Access_Control::get_instance();
                                    $features = $access_control->get_tier_features($tier_key);
                                    ?>
                                    <ul class="sp-feature-list">
                                        <?php foreach (array_slice($features, 0, 5) as $feature_key => $feature): ?>
                                            <li>
                                                <span class="dashicons dashicons-yes"></span>
                                                <?php echo esc_html($feature['name']); ?>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (count($features) > 5): ?>
                                            <li class="sp-more-features">
                                                <?php printf(
                                                    esc_html__('+ %d more features', 'spiralengine'),
                                                    count($features) - 5
                                                ); ?>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="sp-mapping-actions">
                    <button type="submit" class="button button-primary button-large">
                        <?php esc_html_e('Save Mappings', 'spiralengine'); ?>
                    </button>
                    <span class="spinner"></span>
                    <div class="sp-save-message"></div>
                </div>
            </form>
            
            <div class="sp-mapping-info">
                <h3><?php esc_html_e('How Tier Mapping Works', 'spiralengine'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Users are automatically assigned to the highest tier they have access to', 'spiralengine'); ?></li>
                    <li><?php esc_html_e('When subscriptions expire, users revert to their next available tier', 'spiralengine'); ?></li>
                    <li><?php esc_html_e('Discovery tier is the default for all users without active subscriptions', 'spiralengine'); ?></li>
                    <li><?php esc_html_e('Changes take effect immediately for all users', 'spiralengine'); ?></li>
                </ul>
            </div>
            
            <?php if (current_user_can('manage_options')): ?>
                <div class="sp-sync-section">
                    <h3><?php esc_html_e('Synchronization', 'spiralengine'); ?></h3>
                    <p><?php esc_html_e('Force a synchronization of all user tiers based on current mappings.', 'spiralengine'); ?></p>
                    <button type="button" id="sp-sync-all-users" class="button">
                        <?php esc_html_e('Sync All Users', 'spiralengine'); ?>
                    </button>
                    <span class="sp-sync-status"></span>
                </div>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
</div>

<style>
.spiralengine-memberpress-mapping {
    max-width: 1200px;
    margin: 20px 0;
}

.sp-mapping-intro {
    background: #f0f0f1;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.sp-tier-mapping-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.sp-tier-mapping-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.sp-tier-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.sp-tier-header .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    margin-right: 10px;
}

.sp-tier-header h3 {
    margin: 0;
    flex-grow: 1;
}

.sp-tier-level {
    background: #f0f0f1;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.sp-tier-description {
    color: #666;
    margin-bottom: 20px;
}

.sp-membership-selector label {
    display: block;
    font-weight: 600;
    margin-bottom: 10px;
}

.sp-membership-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
}

.sp-membership-option {
    display: flex;
    align-items: center;
    padding: 8px;
    margin: 0 -10px;
    cursor: pointer;
}

.sp-membership-option:hover {
    background: #f5f5f5;
}

.sp-membership-option input[type="checkbox"] {
    margin-right: 10px;
}

.sp-membership-name {
    flex-grow: 1;
}

.sp-membership-meta {
    font-size: 12px;
    color: #666;
    margin-left: 10px;
}

.sp-tier-features {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.sp-tier-features h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
}

.sp-feature-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.sp-feature-list li {
    display: flex;
    align-items: center;
    padding: 4px 0;
    font-size: 13px;
}

.sp-feature-list .dashicons {
    color: #46b450;
    margin-right: 8px;
}

.sp-more-features {
    color: #666;
    font-style: italic;
}

.sp-mapping-actions {
    background: #f0f0f1;
    padding: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.sp-save-message {
    color: #46b450;
}

.sp-mapping-info {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-top: 30px;
}

.sp-sync-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.sp-sync-status {
    margin-left: 10px;
    color: #666;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Save mappings
    $('#spiralengine-tier-mappings').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $spinner = $form.find('.spinner');
        var $message = $form.find('.sp-save-message');
        
        $spinner.addClass('is-active');
        $message.text('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=spiralengine_save_tier_mappings',
            success: function(response) {
                $spinner.removeClass('is-active');
                
                if (response.success) {
                    $message.text(response.data.message);
                    setTimeout(function() {
                        $message.fadeOut();
                    }, 3000);
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });
    
    // Sync all users
    $('#sp-sync-all-users').on('click', function() {
        var $button = $(this);
        var $status = $('.sp-sync-status');
        
        $button.prop('disabled', true);
        $status.text('Syncing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'spiralengine_sync_all_users',
                nonce: $('#spiralengine_nonce').val()
            },
            success: function(response) {
                $button.prop('disabled', false);
                
                if (response.success) {
                    $status.text('Sync complete!');
                } else {
                    $status.text('Sync failed');
                }
                
                setTimeout(function() {
                    $status.fadeOut();
                }, 3000);
            }
        });
    });
});
</script>

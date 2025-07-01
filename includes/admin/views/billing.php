<?php
/**
 * Admin Billing View
 * 
 * @package    SpiralEngine
 * @subpackage Admin/Views
 * @file       includes/admin/views/billing.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get billing data
$billing_stats = spiralengine_get_billing_stats();
$recent_transactions = spiralengine_get_recent_transactions(20);
$subscriptions = spiralengine_get_all_subscriptions();
$revenue_data = spiralengine_get_revenue_analytics();

// Handle actions
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
if ($action === 'refund' && isset($_GET['transaction_id'])) {
    check_admin_referer('refund_transaction_' . $_GET['transaction_id']);
    $result = spiralengine_process_refund($_GET['transaction_id']);
    if ($result) {
        wp_redirect(admin_url('admin.php?page=spiralengine-billing&message=refunded'));
        exit;
    }
}

// Display messages
if (isset($_GET['message'])) {
    $message = '';
    switch ($_GET['message']) {
        case 'refunded':
            $message = __('Transaction refunded successfully.', 'spiralengine');
            break;
    }
    if ($message) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}
?>

<div class="wrap spiralengine-admin-billing">
    <h1><?php _e('Billing & Revenue', 'spiralengine'); ?></h1>
    
    <!-- Revenue Overview -->
    <div class="spiralengine-revenue-overview">
        <div class="revenue-card primary">
            <h3><?php _e('Monthly Recurring Revenue', 'spiralengine'); ?></h3>
            <div class="revenue-amount">$<?php echo number_format($billing_stats['mrr'], 2); ?></div>
            <div class="revenue-change <?php echo $billing_stats['mrr_change'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $billing_stats['mrr_change'] >= 0 ? '+' : ''; ?><?php echo $billing_stats['mrr_change']; ?>% <?php _e('vs last month', 'spiralengine'); ?>
            </div>
        </div>
        
        <div class="revenue-card">
            <h3><?php _e('Annual Run Rate', 'spiralengine'); ?></h3>
            <div class="revenue-amount">$<?php echo number_format($billing_stats['arr'], 2); ?></div>
            <div class="revenue-subtext"><?php _e('Based on current MRR', 'spiralengine'); ?></div>
        </div>
        
        <div class="revenue-card">
            <h3><?php _e('Total Revenue', 'spiralengine'); ?></h3>
            <div class="revenue-amount">$<?php echo number_format($billing_stats['total_revenue'], 2); ?></div>
            <div class="revenue-subtext"><?php _e('All time', 'spiralengine'); ?></div>
        </div>
        
        <div class="revenue-card">
            <h3><?php _e('Active Subscriptions', 'spiralengine'); ?></h3>
            <div class="revenue-amount"><?php echo number_format($billing_stats['active_subscriptions']); ?></div>
            <div class="revenue-subtext"><?php echo $billing_stats['new_subscriptions_today']; ?> <?php _e('new today', 'spiralengine'); ?></div>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="spiralengine-billing-chart">
        <h2><?php _e('Revenue Trends', 'spiralengine'); ?></h2>
        <div class="chart-controls">
            <select id="revenue-period">
                <option value="daily"><?php _e('Daily', 'spiralengine'); ?></option>
                <option value="weekly"><?php _e('Weekly', 'spiralengine'); ?></option>
                <option value="monthly" selected><?php _e('Monthly', 'spiralengine'); ?></option>
                <option value="yearly"><?php _e('Yearly', 'spiralengine'); ?></option>
            </select>
        </div>
        <canvas id="revenue-chart"></canvas>
    </div>

    <!-- Tabs -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="#transactions" class="nav-tab nav-tab-active"><?php _e('Transactions', 'spiralengine'); ?></a>
        <a href="#subscriptions" class="nav-tab"><?php _e('Subscriptions', 'spiralengine'); ?></a>
        <a href="#plans" class="nav-tab"><?php _e('Plans & Pricing', 'spiralengine'); ?></a>
        <a href="#coupons" class="nav-tab"><?php _e('Coupons', 'spiralengine'); ?></a>
    </nav>

    <!-- Transactions Tab -->
    <div id="transactions" class="tab-content active">
        <h2><?php _e('Recent Transactions', 'spiralengine'); ?></h2>
        
        <!-- Filters -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="transaction_type" id="transaction-type-filter">
                    <option value=""><?php _e('All Types', 'spiralengine'); ?></option>
                    <option value="subscription"><?php _e('Subscriptions', 'spiralengine'); ?></option>
                    <option value="one_time"><?php _e('One-time', 'spiralengine'); ?></option>
                    <option value="refund"><?php _e('Refunds', 'spiralengine'); ?></option>
                </select>
                
                <select name="transaction_status" id="transaction-status-filter">
                    <option value=""><?php _e('All Statuses', 'spiralengine'); ?></option>
                    <option value="succeeded"><?php _e('Succeeded', 'spiralengine'); ?></option>
                    <option value="pending"><?php _e('Pending', 'spiralengine'); ?></option>
                    <option value="failed"><?php _e('Failed', 'spiralengine'); ?></option>
                    <option value="refunded"><?php _e('Refunded', 'spiralengine'); ?></option>
                </select>
                
                <button type="button" class="button" id="filter-transactions"><?php _e('Filter', 'spiralengine'); ?></button>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Transaction ID', 'spiralengine'); ?></th>
                    <th><?php _e('Customer', 'spiralengine'); ?></th>
                    <th><?php _e('Amount', 'spiralengine'); ?></th>
                    <th><?php _e('Type', 'spiralengine'); ?></th>
                    <th><?php _e('Status', 'spiralengine'); ?></th>
                    <th><?php _e('Date', 'spiralengine'); ?></th>
                    <th><?php _e('Actions', 'spiralengine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_transactions as $transaction): ?>
                <tr>
                    <td>
                        <code><?php echo esc_html($transaction->transaction_id); ?></code>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=spiralengine-users&action=view&id=' . $transaction->user_id); ?>">
                            <?php echo esc_html($transaction->user_name); ?>
                        </a>
                        <br>
                        <small><?php echo esc_html($transaction->user_email); ?></small>
                    </td>
                    <td>$<?php echo number_format($transaction->amount / 100, 2); ?></td>
                    <td>
                        <span class="transaction-type <?php echo esc_attr($transaction->type); ?>">
                            <?php echo esc_html(ucfirst($transaction->type)); ?>
                        </span>
                    </td>
                    <td>
                        <span class="transaction-status status-<?php echo esc_attr($transaction->status); ?>">
                            <?php echo esc_html(ucfirst($transaction->status)); ?>
                        </span>
                    </td>
                    <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at)); ?></td>
                    <td>
                        <button type="button" class="button button-small view-transaction" data-transaction-id="<?php echo esc_attr($transaction->id); ?>">
                            <?php _e('View', 'spiralengine'); ?>
                        </button>
                        <?php if ($transaction->status === 'succeeded' && $transaction->type !== 'refund'): ?>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=spiralengine-billing&action=refund&transaction_id=' . $transaction->id), 'refund_transaction_' . $transaction->id); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to refund this transaction?', 'spiralengine')); ?>');">
                            <?php _e('Refund', 'spiralengine'); ?>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Subscriptions Tab -->
    <div id="subscriptions" class="tab-content" style="display: none;">
        <h2><?php _e('Active Subscriptions', 'spiralengine'); ?></h2>
        
        <!-- Subscription Stats -->
        <div class="subscription-stats">
            <div class="stat-box">
                <span class="stat-number"><?php echo $billing_stats['subscription_breakdown']['silver']; ?></span>
                <span class="stat-label"><?php _e('Silver', 'spiralengine'); ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo $billing_stats['subscription_breakdown']['gold']; ?></span>
                <span class="stat-label"><?php _e('Gold', 'spiralengine'); ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo $billing_stats['subscription_breakdown']['platinum']; ?></span>
                <span class="stat-label"><?php _e('Platinum', 'spiralengine'); ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo round($billing_stats['churn_rate'], 1); ?>%</span>
                <span class="stat-label"><?php _e('Churn Rate', 'spiralengine'); ?></span>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Customer', 'spiralengine'); ?></th>
                    <th><?php _e('Plan', 'spiralengine'); ?></th>
                    <th><?php _e('Amount', 'spiralengine'); ?></th>
                    <th><?php _e('Status', 'spiralengine'); ?></th>
                    <th><?php _e('Started', 'spiralengine'); ?></th>
                    <th><?php _e('Next Billing', 'spiralengine'); ?></th>
                    <th><?php _e('Actions', 'spiralengine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscriptions as $subscription): ?>
                <tr>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=spiralengine-users&action=view&id=' . $subscription->user_id); ?>">
                            <?php echo esc_html($subscription->user_name); ?>
                        </a>
                    </td>
                    <td>
                        <span class="tier-badge tier-<?php echo esc_attr($subscription->tier); ?>">
                            <?php echo esc_html(ucfirst($subscription->tier)); ?>
                        </span>
                        <span class="billing-period"><?php echo esc_html($subscription->billing_period); ?></span>
                    </td>
                    <td>$<?php echo number_format($subscription->amount / 100, 2); ?>/<?php echo $subscription->billing_period === 'monthly' ? __('mo', 'spiralengine') : __('yr', 'spiralengine'); ?></td>
                    <td>
                        <span class="subscription-status status-<?php echo esc_attr($subscription->status); ?>">
                            <?php echo esc_html(ucfirst($subscription->status)); ?>
                        </span>
                    </td>
                    <td><?php echo date_i18n(get_option('date_format'), strtotime($subscription->created_at)); ?></td>
                    <td>
                        <?php 
                        if ($subscription->status === 'active') {
                            echo date_i18n(get_option('date_format'), strtotime($subscription->next_billing_date));
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td>
                        <button type="button" class="button button-small manage-subscription" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                            <?php _e('Manage', 'spiralengine'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Plans & Pricing Tab -->
    <div id="plans" class="tab-content" style="display: none;">
        <h2><?php _e('Plans & Pricing', 'spiralengine'); ?></h2>
        
        <div class="pricing-plans">
            <?php
            $plans = array(
                'silver' => array(
                    'name' => __('Silver', 'spiralengine'),
                    'monthly' => 9.99,
                    'annual' => 99,
                    'features' => array(
                        __('Unlimited episode tracking', 'spiralengine'),
                        __('All widgets', 'spiralengine'),
                        __('Goal tracking', 'spiralengine'),
                        __('Basic analytics', 'spiralengine')
                    )
                ),
                'gold' => array(
                    'name' => __('Gold', 'spiralengine'),
                    'monthly' => 19.99,
                    'annual' => 199,
                    'features' => array(
                        __('Everything in Silver', 'spiralengine'),
                        __('AI insights', 'spiralengine'),
                        __('API access', 'spiralengine'),
                        __('Priority support', 'spiralengine')
                    )
                ),
                'platinum' => array(
                    'name' => __('Platinum', 'spiralengine'),
                    'monthly' => 39.99,
                    'annual' => 399,
                    'features' => array(
                        __('Everything in Gold', 'spiralengine'),
                        __('Custom AI models', 'spiralengine'),
                        __('White-label options', 'spiralengine'),
                        __('Dedicated support', 'spiralengine')
                    )
                )
            );
            
            foreach ($plans as $plan_id => $plan):
            ?>
            <div class="pricing-plan">
                <h3><?php echo esc_html($plan['name']); ?></h3>
                <div class="plan-pricing">
                    <div class="monthly-price">
                        $<?php echo number_format($plan['monthly'], 2); ?>/<?php _e('month', 'spiralengine'); ?>
                    </div>
                    <div class="annual-price">
                        $<?php echo number_format($plan['annual'], 2); ?>/<?php _e('year', 'spiralengine'); ?>
                    </div>
                </div>
                <ul class="plan-features">
                    <?php foreach ($plan['features'] as $feature): ?>
                    <li><?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="plan-stats">
                    <p><?php printf(__('%d active subscribers', 'spiralengine'), $billing_stats['subscription_breakdown'][$plan_id]); ?></p>
                    <p><?php printf(__('$%s MRR', 'spiralengine'), number_format($billing_stats['mrr_by_plan'][$plan_id], 2)); ?></p>
                </div>
                <button type="button" class="button edit-plan" data-plan="<?php echo esc_attr($plan_id); ?>">
                    <?php _e('Edit Pricing', 'spiralengine'); ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Coupons Tab -->
    <div id="coupons" class="tab-content" style="display: none;">
        <h2><?php _e('Discount Coupons', 'spiralengine'); ?></h2>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=spiralengine-billing&action=new-coupon'); ?>" class="button button-primary">
                <?php _e('Create New Coupon', 'spiralengine'); ?>
            </a>
        </p>
        
        <?php
        $coupons = spiralengine_get_all_coupons();
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Code', 'spiralengine'); ?></th>
                    <th><?php _e('Description', 'spiralengine'); ?></th>
                    <th><?php _e('Discount', 'spiralengine'); ?></th>
                    <th><?php _e('Usage', 'spiralengine'); ?></th>
                    <th><?php _e('Status', 'spiralengine'); ?></th>
                    <th><?php _e('Expires', 'spiralengine'); ?></th>
                    <th><?php _e('Actions', 'spiralengine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coupons as $coupon): ?>
                <tr>
                    <td><code><?php echo esc_html($coupon->code); ?></code></td>
                    <td><?php echo esc_html($coupon->description); ?></td>
                    <td>
                        <?php 
                        if ($coupon->type === 'percentage') {
                            echo $coupon->amount . '%';
                        } else {
                            echo '$' . number_format($coupon->amount / 100, 2);
                        }
                        ?>
                    </td>
                    <td>
                        <?php echo $coupon->usage_count; ?>
                        <?php if ($coupon->usage_limit): ?>
                        / <?php echo $coupon->usage_limit; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="coupon-status status-<?php echo esc_attr($coupon->status); ?>">
                            <?php echo esc_html(ucfirst($coupon->status)); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        if ($coupon->expires_at) {
                            echo date_i18n(get_option('date_format'), strtotime($coupon->expires_at));
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=spiralengine-billing&action=edit-coupon&id=' . $coupon->id); ?>" class="button button-small">
                            <?php _e('Edit', 'spiralengine'); ?>
                        </a>
                        <?php if ($coupon->status === 'active'): ?>
                        <button type="button" class="button button-small deactivate-coupon" data-coupon-id="<?php echo esc_attr($coupon->id); ?>">
                            <?php _e('Deactivate', 'spiralengine'); ?>
                        </button>
                        <?php else: ?>
                        <button type="button" class="button button-small activate-coupon" data-coupon-id="<?php echo esc_attr($coupon->id); ?>">
                            <?php _e('Activate', 'spiralengine'); ?>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Transaction Detail Modal -->
<div id="transaction-modal" class="spiralengine-modal" style="display: none;">
    <div class="spiralengine-modal-content">
        <div class="spiralengine-modal-header">
            <h3><?php _e('Transaction Details', 'spiralengine'); ?></h3>
            <button type="button" class="spiralengine-modal-close">&times;</button>
        </div>
        <div class="spiralengine-modal-body">
            <div id="transaction-details">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Manage Subscription Modal -->
<div id="subscription-modal" class="spiralengine-modal" style="display: none;">
    <div class="spiralengine-modal-content">
        <div class="spiralengine-modal-header">
            <h3><?php _e('Manage Subscription', 'spiralengine'); ?></h3>
            <button type="button" class="spiralengine-modal-close">&times;</button>
        </div>
        <div class="spiralengine-modal-body">
            <div id="subscription-details">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href').substring(1);
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').hide();
        $('#' + target).show();
    });
    
    // Revenue chart
    var revenueData = <?php echo json_encode($revenue_data); ?>;
    var ctx = document.getElementById('revenue-chart').getContext('2d');
    var revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: revenueData.labels,
            datasets: [{
                label: '<?php echo esc_js(__('Revenue', 'spiralengine')); ?>',
                data: revenueData.revenue,
                borderColor: '#4A90E2',
                backgroundColor: 'rgba(74, 144, 226, 0.1)',
                tension: 0.4
            }, {
                label: '<?php echo esc_js(__('Subscriptions', 'spiralengine')); ?>',
                data: revenueData.subscriptions,
                borderColor: '#7ED321',
                backgroundColor: 'rgba(126, 211, 33, 0.1)',
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
    
    // Revenue period change
    $('#revenue-period').on('change', function() {
        // Update chart with new data
        // This would typically make an AJAX call to get new data
    });
    
    // View transaction
    $('.view-transaction').on('click', function() {
        var transactionId = $(this).data('transaction-id');
        
        $.post(ajaxurl, {
            action: 'spiralengine_get_transaction_details',
            transaction_id: transactionId,
            nonce: '<?php echo wp_create_nonce('spiralengine_billing'); ?>'
        }, function(response) {
            if (response.success) {
                $('#transaction-details').html(response.data.html);
                $('#transaction-modal').show();
            }
        });
    });
    
    // Manage subscription
    $('.manage-subscription').on('click', function() {
        var subscriptionId = $(this).data('subscription-id');
        
        $.post(ajaxurl, {
            action: 'spiralengine_get_subscription_details',
            subscription_id: subscriptionId,
            nonce: '<?php echo wp_create_nonce('spiralengine_billing'); ?>'
        }, function(response) {
            if (response.success) {
                $('#subscription-details').html(response.data.html);
                $('#subscription-modal').show();
            }
        });
    });
    
    // Modal close
    $('.spiralengine-modal-close').on('click', function() {
        $(this).closest('.spiralengine-modal').hide();
    });
    
    // Coupon actions
    $('.deactivate-coupon').on('click', function() {
        if (confirm('<?php echo esc_js(__('Deactivate this coupon?', 'spiralengine')); ?>')) {
            var couponId = $(this).data('coupon-id');
            
            $.post(ajaxurl, {
                action: 'spiralengine_toggle_coupon',
                coupon_id: couponId,
                status: 'inactive',
                nonce: '<?php echo wp_create_nonce('spiralengine_coupon'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    });
    
    $('.activate-coupon').on('click', function() {
        var couponId = $(this).data('coupon-id');
        
        $.post(ajaxurl, {
            action: 'spiralengine_toggle_coupon',
            coupon_id: couponId,
            status: 'active',
            nonce: '<?php echo wp_create_nonce('spiralengine_coupon'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        });
    });
});
</script>

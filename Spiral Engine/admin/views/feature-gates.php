<?php
/**
 * Feature Gates Admin View
 * 
 * @package    SPIRAL_Engine
 * @subpackage Admin/Views
 * @file       admin/views/feature-gates.php
 * @since      1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get gates and assignments
$all_gates = $this->get_all_gates();
$dynamic_assignments = $this->get_dynamic_assignments();
$memberpress = SpiralEngine_MemberPress::get_instance();
$tiers = $memberpress->get_all_tiers();
?>

<div class="wrap spiralengine-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Feature Gates Management', 'spiralengine'); ?>
    </h1>
    
    <div class="spiralengine-feature-gates">
        
        <!-- Tabs -->
        <nav class="nav-tab-wrapper wp-clearfix">
            <a href="#overview" class="nav-tab nav-tab-active"><?php esc_html_e('Overview', 'spiralengine'); ?></a>
            <a href="#widgets" class="nav-tab"><?php esc_html_e('Widget Gates', 'spiralengine'); ?></a>
            <a href="#api" class="nav-tab"><?php esc_html_e('API Gates', 'spiralengine'); ?></a>
            <a href="#data" class="nav-tab"><?php esc_html_e('Data Limits', 'spiralengine'); ?></a>
            <a href="#dynamic" class="nav-tab"><?php esc_html_e('Dynamic Assignments', 'spiralengine'); ?></a>
            <a href="#testing" class="nav-tab"><?php esc_html_e('Testing', 'spiralengine'); ?></a>
        </nav>
        
        <!-- Tab Content -->
        <div class="tab-content">
            
            <!-- Overview Tab -->
            <div id="overview" class="tab-pane active">
                <h2><?php esc_html_e('Feature Gate Overview', 'spiralengine'); ?></h2>
                
                <div class="sp-gates-grid">
                    <?php foreach ($tiers as $tier_key => $tier_info): ?>
                        <div class="sp-tier-overview-card" style="border-color: <?php echo esc_attr($tier_info['color']); ?>">
                            <div class="sp-tier-card-header" style="background: <?php echo esc_attr($tier_info['color']); ?>">
                                <span class="dashicons <?php echo esc_attr($tier_info['icon']); ?>"></span>
                                <h3><?php echo esc_html($tier_info['name']); ?></h3>
                            </div>
                            
                            <div class="sp-tier-card-body">
                                <h4><?php esc_html_e('Key Features:', 'spiralengine'); ?></h4>
                                <?php
                                $access_control = SpiralEngine_Access_Control::get_instance();
                                $features = $access_control->get_tier_features($tier_key);
                                ?>
                                <ul class="sp-feature-summary">
                                    <?php foreach (array_slice($features, 0, 6) as $feature): ?>
                                        <li><?php echo esc_html($feature['name']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <div class="sp-tier-stats">
                                    <div class="sp-stat">
                                        <span class="sp-stat-label"><?php esc_html_e('Features', 'spiralengine'); ?></span>
                                        <span class="sp-stat-value"><?php echo count($features); ?></span>
                                    </div>
                                    <div class="sp-stat">
                                        <span class="sp-stat-label"><?php esc_html_e('Users', 'spiralengine'); ?></span>
                                        <span class="sp-stat-value sp-user-count" data-tier="<?php echo esc_attr($tier_key); ?>">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Widget Gates Tab -->
            <div id="widgets" class="tab-pane">
                <h2><?php esc_html_e('Widget Section Gates', 'spiralengine'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Widget Section', 'spiralengine'); ?></th>
                            <th><?php esc_html_e('Required Tiers', 'spiralengine'); ?></th>
                            <th><?php esc_html_e('Restriction Type', 'spiralengine'); ?></th>
                            <th><?php esc_html_e('Preview Content', 'spiralengine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_gates['widgets'] as $widget_type => $sections): ?>
                            <?php if (is_array($sections) && !isset($sections['tiers'])): ?>
                                <?php foreach ($sections as $section_key => $gate): ?>
                                    <?php if (isset($gate['tiers'])): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $widget_type))); ?></strong>
                                                <br>
                                                <span class="description"><?php echo esc_html(ucwords(str_replace('_', ' ', $section_key))); ?></span>
                                            </td>
                                            <td>
                                                <?php foreach ($gate['tiers'] as $tier): ?>
                                                    <?php $tier_info = $memberpress->get_tier_info($tier); ?>
                                                    <span class="sp-tier-badge" style="background: <?php echo esc_attr($tier_info['color']); ?>">
                                                        <?php echo esc_html($tier_info['name']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <span class="sp-restriction-type sp-type-<?php echo esc_attr($gate['gate_type']); ?>">
                                                    <?php echo esc_html(ucwords($gate['gate_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (isset($gate['preview_content']) && $gate['preview_content']): ?>
                                                    <span class="dashicons dashicons-yes" style="color: #46b450;"></span>
                                                <?php else: ?>
                                                    <span class="dashicons dashicons-no-alt" style="color: #ccc;"></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- API Gates Tab -->
            <div id="api" class="tab-pane">
                <h2><?php esc_html_e('API Access Gates', 'spiralengine'); ?></h2>
                
                <div class="sp-api-gates">
                    <h3><?php esc_html_e('Endpoints', 'spiralengine'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Endpoint', 'spiralengine'); ?></th>
                                <th><?php esc_html_e('Required Tier', 'spiralengine'); ?></th>
                                <th><?php esc_html_e('Rate Limit', 'spiralengine'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_gates['api']['endpoints'] as $endpoint => $config): ?>
                                <tr>
                                    <td>
                                        <code>/api/v1/<?php echo esc_html($endpoint); ?></code>
                                    </td>
                                    <td>
                                        <?php foreach ($config['tiers'] as $tier): ?>
                                            <?php $tier_info = $memberpress->get_tier_info($tier); ?>
                                            <span class="sp-tier-badge" style="background: <?php echo esc_attr($tier_info['color']); ?>">
                                                <?php echo esc_html($tier_info['name']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($config['rate_limit'])): ?>
                                            <?php foreach ($config['rate_limit'] as $tier => $limit): ?>
                                                <div><?php echo esc_html($tier); ?>: <?php echo esc_html($limit); ?>/hour</div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="description"><?php esc_html_e('No limit', 'spiralengine'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Data Limits Tab -->
            <div id="data" class="tab-pane">
                <h2><?php esc_html_e('Data Storage Limits', 'spiralengine'); ?></h2>
                
                <div class="sp-data-limits">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Tier', 'spiralengine'); ?></th>
                                <th><?php esc_html_e('Episodes', 'spiralengine'); ?></th>
                                <th><?php esc_html_e('Files', 'spiralengine'); ?></th>
                                <th><?php esc_html_e('Storage', 'spiralengine'); ?></th>
                                <th><?php esc_html_e('Retention', 'spiralengine'); ?></th>
                                <th><?php esc_html_e('AI Tokens/Month', 'spiralengine'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tiers as $tier_key => $tier_info): ?>
                                <?php 
                                $storage = $all_gates['data']['storage_limits'][$tier_key];
                                $retention = $all_gates['data']['retention'][$tier_key];
                                $ai_tokens = $all_gates['ai']['tokens_per_month'][$tier_key];
                                ?>
                                <tr>
                                    <td>
                                        <span class="sp-tier-badge" style="background: <?php echo esc_attr($tier_info['color']); ?>">
                                            <?php echo esc_html($tier_info['name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $storage['episodes'] === 'unlimited' ? '∞' : number_format($storage['episodes']); ?></td>
                                    <td><?php echo $storage['files'] === 'unlimited' ? '∞' : number_format($storage['files']); ?></td>
                                    <td><?php echo esc_html($storage['total_size']); ?></td>
                                    <td>
                                        <?php 
                                        if ($retention === 'forever') {
                                            echo '∞';
                                        } else {
                                            echo sprintf(esc_html__('%d days', 'spiralengine'), $retention);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo number_format($ai_tokens); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Dynamic Assignments Tab -->
            <div id="dynamic" class="tab-pane">
                <h2><?php esc_html_e('Dynamic Feature Assignments', 'spiralengine'); ?></h2>
                
                <div class="sp-dynamic-assignments">
                    <div class="sp-assignment-form">
                        <h3><?php esc_html_e('Add New Assignment', 'spiralengine'); ?></h3>
                        
                        <form id="sp-add-assignment">
                            <?php wp_nonce_field('spiralengine_gates_nonce', 'gates_nonce'); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e('Feature', 'spiralengine'); ?></th>
                                    <td>
                                        <select name="feature" id="sp-assignment-feature" required>
                                            <option value=""><?php esc_html_e('Select a feature...', 'spiralengine'); ?></option>
                                            <?php
                                            $access_control = SpiralEngine_Access_Control::get_instance();
                                            $comparison = $access_control->get_feature_comparison();
                                            foreach ($comparison as $feature_key => $feature_data):
                                            ?>
                                                <option value="<?php echo esc_attr($feature_key); ?>">
                                                    <?php echo esc_html($feature_data['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Assignment Type', 'spiralengine'); ?></th>
                                    <td>
                                        <label>
                                            <input type="radio" name="type" value="user" checked>
                                            <?php esc_html_e('Specific User', 'spiralengine'); ?>
                                        </label>
                                        <label>
                                            <input type="radio" name="type" value="role">
                                            <?php esc_html_e('User Role', 'spiralengine'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="sp-user-selector">
                                    <th><?php esc_html_e('User', 'spiralengine'); ?></th>
                                    <td>
                                        <input type="text" 
                                               id="sp-user-search" 
                                               placeholder="<?php esc_attr_e('Search by username or email...', 'spiralengine'); ?>">
                                        <input type="hidden" name="user_id" id="sp-selected-user">
                                        <div id="sp-user-results"></div>
                                    </td>
                                </tr>
                                <tr class="sp-role-selector" style="display:none;">
                                    <th><?php esc_html_e('Role', 'spiralengine'); ?></th>
                                    <td>
                                        <select name="role">
                                            <?php wp_dropdown_roles(); ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Expiration', 'spiralengine'); ?></th>
                                    <td>
                                        <input type="number" 
                                               name="expiry" 
                                               min="0" 
                                               value="0">
                                        <span class="description">
                                            <?php esc_html_e('Days until expiration (0 = never)', 'spiralengine'); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary">
                                    <?php esc_html_e('Add Assignment', 'spiralengine'); ?>
                                </button>
                            </p>
                        </form>
                    </div>
                    
                    <div class="sp-current-assignments">
                        <h3><?php esc_html_e('Current Assignments', 'spiralengine'); ?></h3>
                        
                        <?php if (!empty($dynamic_assignments)): ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Feature', 'spiralengine'); ?></th>
                                        <th><?php esc_html_e('Assigned To', 'spiralengine'); ?></th>
                                        <th><?php esc_html_e('Expires', 'spiralengine'); ?></th>
                                        <th><?php esc_html_e('Actions', 'spiralengine'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dynamic_assignments as $feature => $assignments): ?>
                                        <?php if (!empty($assignments['users'])): ?>
                                            <?php foreach ($assignments['users'] as $user_id): ?>
                                                <?php $user = get_user_by('id', $user_id); ?>
                                                <tr>
                                                    <td><?php echo esc_html($feature); ?></td>
                                                    <td>
                                                        <?php if ($user): ?>
                                                            <?php echo esc_html($user->display_name); ?> 
                                                            (<?php echo esc_html($user->user_email); ?>)
                                                        <?php else: ?>
                                                            <?php esc_html_e('Unknown User', 'spiralengine'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if (isset($assignments['user_expiry'][$user_id])):
                                                            echo esc_html(date_i18n(get_option('date_format'), $assignments['user_expiry'][$user_id]));
                                                        else:
                                                            esc_html_e('Never', 'spiralengine');
                                                        endif;
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <button class="button button-small sp-remove-assignment" 
                                                                data-feature="<?php echo esc_attr($feature); ?>"
                                                                data-user="<?php echo esc_attr($user_id); ?>">
                                                            <?php esc_html_e('Remove', 'spiralengine'); ?>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($assignments['roles'])): ?>
                                            <?php foreach ($assignments['roles'] as $role): ?>
                                                <tr>
                                                    <td><?php echo esc_html($feature); ?></td>
                                                    <td>
                                                        <?php esc_html_e('Role:', 'spiralengine'); ?> 
                                                        <?php echo esc_html(translate_user_role(wp_roles()->roles[$role]['name'])); ?>
                                                    </td>
                                                    <td><?php esc_html_e('Never', 'spiralengine'); ?></td>
                                                    <td>
                                                        <button class="button button-small sp-remove-assignment" 
                                                                data-feature="<?php echo esc_attr($feature); ?>"
                                                                data-role="<?php echo esc_attr($role); ?>">
                                                            <?php esc_html_e('Remove', 'spiralengine'); ?>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="description">
                                <?php esc_html_e('No dynamic assignments configured.', 'spiralengine'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Testing Tab -->
            <div id="testing" class="tab-pane">
                <h2><?php esc_html_e('Gate Testing', 'spiralengine'); ?></h2>
                
                <div class="sp-gate-testing">
                    <form id="sp-test-gate">
                        <?php wp_nonce_field('spiralengine_gates_nonce', 'test_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Test User', 'spiralengine'); ?></th>
                                <td>
                                    <input type="text" 
                                           id="sp-test-user-search" 
                                           placeholder="<?php esc_attr_e('Search for user...', 'spiralengine'); ?>">
                                    <input type="hidden" id="sp-test-user-id">
                                    <div id="sp-test-user-info"></div>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Feature to Test', 'spiralengine'); ?></th>
                                <td>
                                    <select id="sp-test-feature">
                                        <option value=""><?php esc_html_e('Select a feature...', 'spiralengine'); ?></option>
                                        <?php foreach ($comparison as $feature_key => $feature_data): ?>
                                            <option value="<?php echo esc_attr($feature_key); ?>">
                                                <?php echo esc_html($feature_data['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Test Access', 'spiralengine'); ?>
                            </button>
                        </p>
                    </form>
                    
                    <div id="sp-test-results" style="display:none;">
                        <h3><?php esc_html_e('Test Results', 'spiralengine'); ?></h3>
                        <div class="sp-test-output"></div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<style>
/* Tab Navigation */
.tab-content .tab-pane {
    display: none;
    padding: 20px 0;
}

.tab-content .tab-pane.active {
    display: block;
}

/* Overview Grid */
.sp-gates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.sp-tier-overview-card {
    background: #fff;
    border: 2px solid;
    border-radius: 8px;
    overflow: hidden;
}

.sp-tier-card-header {
    color: #fff;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sp-tier-card-header .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.sp-tier-card-header h3 {
    margin: 0;
    color: #fff;
}

.sp-tier-card-body {
    padding: 20px;
}

.sp-feature-summary {
    list-style: none;
    margin: 10px 0;
    padding: 0;
}

.sp-feature-summary li {
    padding: 4px 0;
    font-size: 13px;
}

.sp-tier-stats {
    display: flex;
    gap: 20px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.sp-stat {
    text-align: center;
    flex: 1;
}

.sp-stat-label {
    display: block;
    font-size: 12px;
    color: #666;
}

.sp-stat-value {
    display: block;
    font-size: 24px;
    font-weight: 600;
    color: #333;
}

/* Tier Badges */
.sp-tier-badge {
    display: inline-block;
    padding: 3px 8px;
    color: #fff;
    border-radius: 3px;
    font-size: 12px;
    margin-right: 5px;
}

/* Restriction Types */
.sp-restriction-type {
    display: inline-block;
    padding: 3px 8px;
    background: #f0f0f1;
    border-radius: 3px;
    font-size: 12px;
}

.sp-type-blur { background: #e1f5fe; color: #0277bd; }
.sp-type-visual { background: #f3e5f5; color: #6a1b9a; }
.sp-type-functional { background: #fff3e0; color: #e65100; }
.sp-type-hide { background: #ffebee; color: #b71c1c; }

/* Forms */
.sp-assignment-form,
.sp-gate-testing {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

#sp-user-results,
#sp-test-user-info {
    margin-top: 10px;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 4px;
    display: none;
}

/* Test Results */
.sp-test-output {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 4px;
    font-family: monospace;
}

.sp-test-success {
    color: #46b450;
}

.sp-test-fail {
    color: #dc3232;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');
    });
    
    // Assignment type toggle
    $('input[name="type"]').on('change', function() {
        if ($(this).val() === 'user') {
            $('.sp-user-selector').show();
            $('.sp-role-selector').hide();
        } else {
            $('.sp-user-selector').hide();
            $('.sp-role-selector').show();
        }
    });
    
    // User search
    var userSearchTimeout;
    $('#sp-user-search, #sp-test-user-search').on('keyup', function() {
        var $input = $(this);
        var query = $input.val();
        var isTest = $input.attr('id') === 'sp-test-user-search';
        
        clearTimeout(userSearchTimeout);
        
        if (query.length < 2) {
            return;
        }
        
        userSearchTimeout = setTimeout(function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spiralengine_search_users',
                    query: query,
                    nonce: $('#gates_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        var resultsHtml = '<ul>';
                        response.data.forEach(function(user) {
                            resultsHtml += '<li data-id="' + user.ID + '">' +
                                         user.display_name + ' (' + user.user_email + ')' +
                                         '</li>';
                        });
                        resultsHtml += '</ul>';
                        
                        if (isTest) {
                            $('#sp-test-user-info').html(resultsHtml).show();
                        } else {
                            $('#sp-user-results').html(resultsHtml).show();
                        }
                    }
                }
            });
        }, 300);
    });
    
    // User selection
    $(document).on('click', '#sp-user-results li, #sp-test-user-info li', function() {
        var userId = $(this).data('id');
        var userName = $(this).text();
        var isTest = $(this).closest('#sp-test-user-info').length > 0;
        
        if (isTest) {
            $('#sp-test-user-id').val(userId);
            $('#sp-test-user-search').val(userName);
            $('#sp-test-user-info').hide();
        } else {
            $('#sp-selected-user').val(userId);
            $('#sp-user-search').val(userName);
            $('#sp-user-results').hide();
        }
    });
    
    // Add assignment
    $('#sp-add-assignment').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=spiralengine_update_feature_gate';
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });
    
    // Test gate
    $('#sp-test-gate').on('submit', function(e) {
        e.preventDefault();
        
        var userId = $('#sp-test-user-id').val();
        var feature = $('#sp-test-feature').val();
        
        if (!userId || !feature) {
            alert('Please select both a user and a feature to test.');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'spiralengine_test_feature_gate',
                user_id: userId,
                feature: feature,
                nonce: $('#test_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var resultHtml = '<div class="' + (data.can_access ? 'sp-test-success' : 'sp-test-fail') + '">';
                    resultHtml += '<strong>Access: ' + (data.can_access ? 'GRANTED' : 'DENIED') + '</strong><br>';
                    resultHtml += 'User Tier: ' + data.user_tier + '<br>';
                    resultHtml += 'User Email: ' + data.user_email + '<br>';
                    if (data.dynamic_assignment) {
                        resultHtml += '<em>Has dynamic assignment</em>';
                    }
                    resultHtml += '</div>';
                    
                    $('.sp-test-output').html(resultHtml);
                    $('#sp-test-results').show();
                }
            }
        });
    });
    
    // Load user counts
    function loadUserCounts() {
        $('.sp-user-count').each(function() {
            var $this = $(this);
            var tier = $this.data('tier');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'spiralengine_get_tier_user_count',
                    tier: tier,
                    nonce: '<?php echo wp_create_nonce('spiralengine_ajax_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $this.text(response.data.count);
                    }
                }
            });
        });
    }
    
    loadUserCounts();
});
</script>

<?php
/**
 * Admin Widgets View
 * 
 * @package    SpiralEngine
 * @subpackage Admin/Views
 * @file       includes/admin/views/widgets.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle actions
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$widget_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process form submissions
if (isset($_POST['submit']) && $action === 'new') {
    check_admin_referer('spiralengine_create_widget');
    $result = spiralengine_create_widget($_POST);
    if ($result) {
        wp_redirect(admin_url('admin.php?page=spiralengine-widgets&message=created'));
        exit;
    }
}

if (isset($_POST['submit']) && $action === 'edit') {
    check_admin_referer('spiralengine_edit_widget');
    $result = spiralengine_update_widget($widget_id, $_POST);
    if ($result) {
        wp_redirect(admin_url('admin.php?page=spiralengine-widgets&message=updated'));
        exit;
    }
}

// Display messages
if (isset($_GET['message'])) {
    $message = '';
    switch ($_GET['message']) {
        case 'created':
            $message = __('Widget created successfully.', 'spiralengine');
            break;
        case 'updated':
            $message = __('Widget updated successfully.', 'spiralengine');
            break;
        case 'deleted':
            $message = __('Widget deleted successfully.', 'spiralengine');
            break;
        case 'activated':
            $message = __('Widget activated successfully.', 'spiralengine');
            break;
        case 'deactivated':
            $message = __('Widget deactivated successfully.', 'spiralengine');
            break;
    }
    if ($message) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}
?>

<div class="wrap spiralengine-admin-widgets">
    <?php if ($action === 'list'): ?>
        <h1 class="wp-heading-inline"><?php _e('Widgets', 'spiralengine'); ?></h1>
        <a href="<?php echo admin_url('admin.php?page=spiralengine-widgets&action=new'); ?>" class="page-title-action">
            <?php _e('Add New', 'spiralengine'); ?>
        </a>
        <hr class="wp-header-end">
        
        <?php
        // Get widgets
        $widgets = spiralengine_get_all_widgets();
        $active_widgets = array_filter($widgets, function($w) { return $w->status === 'active'; });
        $inactive_widgets = array_filter($widgets, function($w) { return $w->status !== 'active'; });
        ?>
        
        <div class="spiralengine-widget-stats">
            <div class="stat-box">
                <span class="stat-number"><?php echo count($widgets); ?></span>
                <span class="stat-label"><?php _e('Total Widgets', 'spiralengine'); ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo count($active_widgets); ?></span>
                <span class="stat-label"><?php _e('Active', 'spiralengine'); ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo count($inactive_widgets); ?></span>
                <span class="stat-label"><?php _e('Inactive', 'spiralengine'); ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo spiralengine_get_total_widget_episodes(); ?></span>
                <span class="stat-label"><?php _e('Total Episodes', 'spiralengine'); ?></span>
            </div>
        </div>
        
        <div class="spiralengine-widgets-grid">
            <?php foreach ($widgets as $widget): 
                $widget_stats = spiralengine_get_widget_stats($widget->id);
            ?>
            <div class="spiralengine-widget-card <?php echo $widget->status !== 'active' ? 'inactive' : ''; ?>">
                <div class="widget-header">
                    <h3><?php echo esc_html($widget->name); ?></h3>
                    <div class="widget-actions">
                        <a href="<?php echo admin_url('admin.php?page=spiralengine-widgets&action=edit&id=' . $widget->id); ?>" class="button button-small">
                            <?php _e('Edit', 'spiralengine'); ?>
                        </a>
                        <?php if ($widget->status === 'active'): ?>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=spiralengine-widgets&action=deactivate&id=' . $widget->id), 'deactivate_widget_' . $widget->id); ?>" class="button button-small">
                            <?php _e('Deactivate', 'spiralengine'); ?>
                        </a>
                        <?php else: ?>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=spiralengine-widgets&action=activate&id=' . $widget->id), 'activate_widget_' . $widget->id); ?>" class="button button-small button-primary">
                            <?php _e('Activate', 'spiralengine'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="widget-content">
                    <p class="widget-description"><?php echo esc_html($widget->description); ?></p>
                    
                    <div class="widget-stats">
                        <div class="stat">
                            <span class="stat-value"><?php echo number_format($widget_stats['total_episodes']); ?></span>
                            <span class="stat-label"><?php _e('Episodes', 'spiralengine'); ?></span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo number_format($widget_stats['active_users']); ?></span>
                            <span class="stat-label"><?php _e('Users', 'spiralengine'); ?></span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo round($widget_stats['avg_severity'], 1); ?></span>
                            <span class="stat-label"><?php _e('Avg Severity', 'spiralengine'); ?></span>
                        </div>
                    </div>
                    
                    <div class="widget-meta">
                        <span class="widget-type">
                            <strong><?php _e('Type:', 'spiralengine'); ?></strong> 
                            <?php echo esc_html(ucfirst($widget->type)); ?>
                        </span>
                        <span class="widget-shortcode">
                            <strong><?php _e('Shortcode:', 'spiralengine'); ?></strong>
                            <code>[spiralengine_widget id="<?php echo $widget->id; ?>"]</code>
                            <button type="button" class="copy-shortcode" data-shortcode='[spiralengine_widget id="<?php echo $widget->id; ?>"]'>
                                <?php _e('Copy', 'spiralengine'); ?>
                            </button>
                        </span>
                    </div>
                    
                    <?php if ($widget->status !== 'active'): ?>
                    <div class="widget-inactive-notice">
                        <?php _e('This widget is currently inactive', 'spiralengine'); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="widget-footer">
                    <span class="widget-created">
                        <?php _e('Created:', 'spiralengine'); ?> 
                        <?php echo date_i18n(get_option('date_format'), strtotime($widget->created_at)); ?>
                    </span>
                    <span class="widget-modified">
                        <?php _e('Modified:', 'spiralengine'); ?> 
                        <?php echo human_time_diff(strtotime($widget->updated_at), current_time('timestamp')) . ' ' . __('ago', 'spiralengine'); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($widgets)): ?>
        <div class="spiralengine-empty-state">
            <img src="<?php echo SPIRALENGINE_PLUGIN_URL; ?>assets/images/empty-widgets.svg" alt="No widgets">
            <h3><?php _e('No widgets yet', 'spiralengine'); ?></h3>
            <p><?php _e('Create your first widget to start tracking mental health episodes.', 'spiralengine'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=spiralengine-widgets&action=new'); ?>" class="button button-primary button-hero">
                <?php _e('Create Your First Widget', 'spiralengine'); ?>
            </a>
        </div>
        <?php endif; ?>
        
    <?php elseif ($action === 'new' || $action === 'edit'): 
        if ($action === 'edit') {
            $widget = spiralengine_get_widget($widget_id);
            if (!$widget) {
                wp_die(__('Widget not found.', 'spiralengine'));
            }
        } else {
            $widget = (object) array(
                'name' => '',
                'description' => '',
                'type' => 'mood',
                'config' => array(),
                'status' => 'active'
            );
        }
    ?>
        <h1>
            <?php 
            if ($action === 'edit') {
                _e('Edit Widget', 'spiralengine');
            } else {
                _e('Add New Widget', 'spiralengine');
            }
            ?>
        </h1>
        
        <form method="post" action="" class="spiralengine-widget-form">
            <?php wp_nonce_field($action === 'edit' ? 'spiralengine_edit_widget' : 'spiralengine_create_widget'); ?>
            
            <div class="spiralengine-form-section">
                <h2><?php _e('Basic Information', 'spiralengine'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="widget_name"><?php _e('Widget Name', 'spiralengine'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="text" name="widget_name" id="widget_name" class="regular-text" value="<?php echo esc_attr($widget->name); ?>" required>
                            <p class="description"><?php _e('Give your widget a descriptive name.', 'spiralengine'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="widget_description"><?php _e('Description', 'spiralengine'); ?></label></th>
                        <td>
                            <textarea name="widget_description" id="widget_description" rows="3" class="large-text"><?php echo esc_textarea($widget->description); ?></textarea>
                            <p class="description"><?php _e('Briefly describe what this widget tracks.', 'spiralengine'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="widget_type"><?php _e('Widget Type', 'spiralengine'); ?> <span class="required">*</span></label></th>
                        <td>
                            <select name="widget_type" id="widget_type" required <?php echo $action === 'edit' ? 'disabled' : ''; ?>>
                                <option value="mood" <?php selected($widget->type, 'mood'); ?>><?php _e('Mood Tracker', 'spiralengine'); ?></option>
                                <option value="anxiety" <?php selected($widget->type, 'anxiety'); ?>><?php _e('Anxiety Tracker', 'spiralengine'); ?></option>
                                <option value="panic" <?php selected($widget->type, 'panic'); ?>><?php _e('Panic Attack Log', 'spiralengine'); ?></option>
                                <option value="sleep" <?php selected($widget->type, 'sleep'); ?>><?php _e('Sleep Quality', 'spiralengine'); ?></option>
                                <option value="medication" <?php selected($widget->type, 'medication'); ?>><?php _e('Medication Adherence', 'spiralengine'); ?></option>
                                <option value="custom" <?php selected($widget->type, 'custom'); ?>><?php _e('Custom Tracker', 'spiralengine'); ?></option>
                            </select>
                            <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="widget_type" value="<?php echo esc_attr($widget->type); ?>">
                            <p class="description"><?php _e('Widget type cannot be changed after creation.', 'spiralengine'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Status', 'spiralengine'); ?></th>
                        <td>
                            <label for="widget_active">
                                <input type="checkbox" name="widget_active" id="widget_active" value="1" <?php checked($widget->status, 'active'); ?>>
                                <?php _e('Active', 'spiralengine'); ?>
                            </label>
                            <p class="description"><?php _e('Only active widgets can be used by members.', 'spiralengine'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="spiralengine-form-section">
                <h2><?php _e('Widget Configuration', 'spiralengine'); ?></h2>
                
                <div id="widget-config-fields">
                    <!-- Dynamic fields based on widget type -->
                </div>
            </div>
            
            <div class="spiralengine-form-section">
                <h2><?php _e('Display Settings', 'spiralengine'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Display Options', 'spiralengine'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="config[show_severity]" value="1" <?php checked($widget->config['show_severity'] ?? true); ?>>
                                    <?php _e('Show severity scale', 'spiralengine'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="config[show_triggers]" value="1" <?php checked($widget->config['show_triggers'] ?? true); ?>>
                                    <?php _e('Include trigger tracking', 'spiralengine'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="config[show_notes]" value="1" <?php checked($widget->config['show_notes'] ?? true); ?>>
                                    <?php _e('Allow notes/comments', 'spiralengine'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="config[show_time]" value="1" <?php checked($widget->config['show_time'] ?? true); ?>>
                                    <?php _e('Include time of day', 'spiralengine'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="config[anonymous_mode]" value="1" <?php checked($widget->config['anonymous_mode'] ?? false); ?>>
                                    <?php _e('Anonymous mode (no user data stored)', 'spiralengine'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="button_text"><?php _e('Button Text', 'spiralengine'); ?></label></th>
                        <td>
                            <input type="text" name="config[button_text]" id="button_text" class="regular-text" value="<?php echo esc_attr($widget->config['button_text'] ?? __('Track Episode', 'spiralengine')); ?>">
                            <p class="description"><?php _e('Text displayed on the submit button.', 'spiralengine'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="success_message"><?php _e('Success Message', 'spiralengine'); ?></label></th>
                        <td>
                            <input type="text" name="config[success_message]" id="success_message" class="large-text" value="<?php echo esc_attr($widget->config['success_message'] ?? __('Episode tracked successfully!', 'spiralengine')); ?>">
                            <p class="description"><?php _e('Message shown after successful submission.', 'spiralengine'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="spiralengine-form-section">
                <h2><?php _e('Access Control', 'spiralengine'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Who can use this widget?', 'spiralengine'); ?></th>
                        <td>
                            <select name="config[access_level]" class="regular-text">
                                <option value="all" <?php selected($widget->config['access_level'] ?? 'all', 'all'); ?>><?php _e('All members', 'spiralengine'); ?></option>
                                <option value="free" <?php selected($widget->config['access_level'] ?? '', 'free'); ?>><?php _e('Free tier only', 'spiralengine'); ?></option>
                                <option value="paid" <?php selected($widget->config['access_level'] ?? '', 'paid'); ?>><?php _e('Paid tiers only', 'spiralengine'); ?></option>
                                <option value="silver" <?php selected($widget->config['access_level'] ?? '', 'silver'); ?>><?php _e('Silver and above', 'spiralengine'); ?></option>
                                <option value="gold" <?php selected($widget->config['access_level'] ?? '', 'gold'); ?>><?php _e('Gold and above', 'spiralengine'); ?></option>
                                <option value="platinum" <?php selected($widget->config['access_level'] ?? '', 'platinum'); ?>><?php _e('Platinum only', 'spiralengine'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Usage Limits', 'spiralengine'); ?></th>
                        <td>
                            <label><?php _e('Max episodes per day:', 'spiralengine'); ?></label>
                            <input type="number" name="config[daily_limit]" min="0" max="100" class="small-text" value="<?php echo esc_attr($widget->config['daily_limit'] ?? 0); ?>">
                            <span class="description"><?php _e('(0 = unlimited)', 'spiralengine'); ?></span>
                            
                            <br><br>
                            
                            <label><?php _e('Cooldown period (minutes):', 'spiralengine'); ?></label>
                            <input type="number" name="config[cooldown]" min="0" max="1440" class="small-text" value="<?php echo esc_attr($widget->config['cooldown'] ?? 0); ?>">
                            <span class="description"><?php _e('(0 = no cooldown)', 'spiralengine'); ?></span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" value="<?php echo $action === 'edit' ? esc_attr__('Update Widget', 'spiralengine') : esc_attr__('Create Widget', 'spiralengine'); ?>">
                <a href="<?php echo admin_url('admin.php?page=spiralengine-widgets'); ?>" class="button"><?php _e('Cancel', 'spiralengine'); ?></a>
            </p>
        </form>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Copy shortcode
    $('.copy-shortcode').on('click', function() {
        var shortcode = $(this).data('shortcode');
        var temp = $('<input>');
        $('body').append(temp);
        temp.val(shortcode).select();
        document.execCommand('copy');
        temp.remove();
        
        $(this).text('<?php echo esc_js(__('Copied!', 'spiralengine')); ?>');
        setTimeout(() => {
            $(this).text('<?php echo esc_js(__('Copy', 'spiralengine')); ?>');
        }, 2000);
    });
    
    // Widget type configuration
    function loadWidgetConfig(type) {
        var config = $('#widget-config-fields');
        config.empty();
        
        switch(type) {
            case 'mood':
                config.html(`
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_js(__('Mood Scale', 'spiralengine')); ?></th>
                            <td>
                                <select name="config[mood_scale]" class="regular-text">
                                    <option value="1-10"><?php echo esc_js(__('1-10 (Numeric)', 'spiralengine')); ?></option>
                                    <option value="emoji"><?php echo esc_js(__('Emoji Scale', 'spiralengine')); ?></option>
                                    <option value="descriptive"><?php echo esc_js(__('Descriptive (Very Bad to Very Good)', 'spiralengine')); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_js(__('Track Multiple Moods', 'spiralengine')); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="config[multiple_moods]" value="1">
                                    <?php echo esc_js(__('Allow tracking multiple mood states', 'spiralengine')); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                `);
                break;
                
            case 'anxiety':
                config.html(`
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_js(__('Anxiety Symptoms', 'spiralengine')); ?></th>
                            <td>
                                <fieldset>
                                    <label><input type="checkbox" name="config[symptoms][]" value="racing_thoughts" checked> <?php echo esc_js(__('Racing thoughts', 'spiralengine')); ?></label><br>
                                    <label><input type="checkbox" name="config[symptoms][]" value="physical" checked> <?php echo esc_js(__('Physical symptoms', 'spiralengine')); ?></label><br>
                                    <label><input type="checkbox" name="config[symptoms][]" value="avoidance" checked> <?php echo esc_js(__('Avoidance behaviors', 'spiralengine')); ?></label><br>
                                    <label><input type="checkbox" name="config[symptoms][]" value="panic" checked> <?php echo esc_js(__('Panic symptoms', 'spiralengine')); ?></label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                `);
                break;
                
            case 'sleep':
                config.html(`
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_js(__('Sleep Metrics', 'spiralengine')); ?></th>
                            <td>
                                <fieldset>
                                    <label><input type="checkbox" name="config[metrics][]" value="duration" checked> <?php echo esc_js(__('Sleep duration', 'spiralengine')); ?></label><br>
                                    <label><input type="checkbox" name="config[metrics][]" value="quality" checked> <?php echo esc_js(__('Sleep quality', 'spiralengine')); ?></label><br>
                                    <label><input type="checkbox" name="config[metrics][]" value="interruptions" checked> <?php echo esc_js(__('Number of interruptions', 'spiralengine')); ?></label><br>
                                    <label><input type="checkbox" name="config[metrics][]" value="dreams" checked> <?php echo esc_js(__('Dream recall', 'spiralengine')); ?></label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                `);
                break;
                
            case 'custom':
                config.html(`
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_js(__('Custom Fields', 'spiralengine')); ?></th>
                            <td>
                                <div id="custom-fields-container">
                                    <div class="custom-field-item">
                                        <input type="text" name="config[custom_fields][0][label]" placeholder="<?php echo esc_attr__('Field Label', 'spiralengine'); ?>" class="regular-text">
                                        <select name="config[custom_fields][0][type]">
                                            <option value="text"><?php echo esc_js(__('Text', 'spiralengine')); ?></option>
                                            <option value="number"><?php echo esc_js(__('Number', 'spiralengine')); ?></option>
                                            <option value="scale"><?php echo esc_js(__('Scale', 'spiralengine')); ?></option>
                                            <option value="checkbox"><?php echo esc_js(__('Checkbox', 'spiralengine')); ?></option>
                                        </select>
                                        <button type="button" class="button remove-field"><?php echo esc_js(__('Remove', 'spiralengine')); ?></button>
                                    </div>
                                </div>
                                <button type="button" class="button" id="add-custom-field"><?php echo esc_js(__('Add Field', 'spiralengine')); ?></button>
                            </td>
                        </tr>
                    </table>
                `);
                break;
        }
    }
    
    // Initialize widget config
    if ($('#widget_type').length) {
        loadWidgetConfig($('#widget_type').val());
        
        $('#widget_type').on('change', function() {
            loadWidgetConfig($(this).val());
        });
    }
    
    // Custom fields functionality
    $(document).on('click', '#add-custom-field', function() {
        var container = $('#custom-fields-container');
        var index = container.find('.custom-field-item').length;
        
        var fieldHtml = `
            <div class="custom-field-item">
                <input type="text" name="config[custom_fields][${index}][label]" placeholder="<?php echo esc_attr__('Field Label', 'spiralengine'); ?>" class="regular-text">
                <select name="config[custom_fields][${index}][type]">
                    <option value="text"><?php echo esc_js(__('Text', 'spiralengine')); ?></option>
                    <option value="number"><?php echo esc_js(__('Number', 'spiralengine')); ?></option>
                    <option value="scale"><?php echo esc_js(__('Scale', 'spiralengine')); ?></option>
                    <option value="checkbox"><?php echo esc_js(__('Checkbox', 'spiralengine')); ?></option>
                </select>
                <button type="button" class="button remove-field"><?php echo esc_js(__('Remove', 'spiralengine')); ?></button>
            </div>
        `;
        
        container.append(fieldHtml);
    });
    
    $(document).on('click', '.remove-field', function() {
        $(this).closest('.custom-field-item').remove();
    });
});
</script>


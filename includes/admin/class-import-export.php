<?php
/**
 * SpiralEngine Import/Export Functionality
 *
 * @package    SpiralEngine
 * @subpackage Admin
 * @file       includes/admin/class-import-export.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SpiralEngine_Import_Export
 *
 * Handles data import and export functionality
 */
class SpiralEngine_Import_Export {
    
    /**
     * Export types
     *
     * @var array
     */
    private $export_types = array();
    
    /**
     * Import types
     *
     * @var array
     */
    private $import_types = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize import/export
     */
    public function init() {
        $this->setup_types();
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'process_export'));
        add_action('admin_init', array($this, 'process_import'));
        add_action('wp_ajax_spiralengine_export_preview', array($this, 'ajax_export_preview'));
        add_action('wp_ajax_spiralengine_validate_import', array($this, 'ajax_validate_import'));
        add_action('wp_ajax_spiralengine_process_import_chunk', array($this, 'ajax_process_import_chunk'));
        add_action('wp_ajax_spiralengine_get_export_progress', array($this, 'ajax_get_export_progress'));
    }
    
    /**
     * Setup export/import types
     */
    private function setup_types() {
        $this->export_types = array(
            'user_data' => array(
                'label' => __('User Data', 'spiralengine'),
                'description' => __('Export all user episodes, goals, and preferences', 'spiralengine'),
                'capability' => 'spiralengine_export_data',
                'formats' => array('csv', 'json', 'xml')
            ),
            'episodes' => array(
                'label' => __('Episodes', 'spiralengine'),
                'description' => __('Export episode data with filters', 'spiralengine'),
                'capability' => 'spiralengine_export_data',
                'formats' => array('csv', 'json', 'pdf')
            ),
            'analytics' => array(
                'label' => __('Analytics Report', 'spiralengine'),
                'description' => __('Export comprehensive analytics data', 'spiralengine'),
                'capability' => 'spiralengine_view_analytics',
                'formats' => array('csv', 'pdf', 'xlsx')
            ),
            'settings' => array(
                'label' => __('Plugin Settings', 'spiralengine'),
                'description' => __('Export all plugin configuration', 'spiralengine'),
                'capability' => 'spiralengine_manage_settings',
                'formats' => array('json')
            ),
            'widgets' => array(
                'label' => __('Widget Configuration', 'spiralengine'),
                'description' => __('Export widget settings and layouts', 'spiralengine'),
                'capability' => 'spiralengine_manage_widgets',
                'formats' => array('json')
            ),
            'complete_backup' => array(
                'label' => __('Complete Backup', 'spiralengine'),
                'description' => __('Full backup of all SpiralEngine data', 'spiralengine'),
                'capability' => 'spiralengine_manage_settings',
                'formats' => array('zip')
            )
        );
        
        $this->import_types = array(
            'user_data' => array(
                'label' => __('User Data', 'spiralengine'),
                'description' => __('Import user episodes and data', 'spiralengine'),
                'capability' => 'spiralengine_import_data',
                'formats' => array('csv', 'json', 'xml')
            ),
            'settings' => array(
                'label' => __('Plugin Settings', 'spiralengine'),
                'description' => __('Import plugin configuration', 'spiralengine'),
                'capability' => 'spiralengine_manage_settings',
                'formats' => array('json')
            ),
            'widgets' => array(
                'label' => __('Widget Configuration', 'spiralengine'),
                'description' => __('Import widget settings', 'spiralengine'),
                'capability' => 'spiralengine_manage_widgets',
                'formats' => array('json')
            ),
            'restore_backup' => array(
                'label' => __('Restore Backup', 'spiralengine'),
                'description' => __('Restore from complete backup', 'spiralengine'),
                'capability' => 'spiralengine_manage_settings',
                'formats' => array('zip')
            )
        );
    }
    
    /**
     * Enqueue scripts and styles
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'spiralengine-import-export') === false) {
            return;
        }
        
        wp_enqueue_style('spiralengine-admin');
        wp_enqueue_script('spiralengine-admin');
        wp_enqueue_script('jquery-ui-progressbar');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Add import/export specific script
        wp_add_inline_script('spiralengine-admin', $this->get_inline_script());
        
        // Localize script
        wp_localize_script('spiralengine-admin', 'spiralengine_import_export', array(
            'nonce' => wp_create_nonce('spiralengine_import_export'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'strings' => array(
                'export_started' => __('Export started...', 'spiralengine'),
                'export_complete' => __('Export complete!', 'spiralengine'),
                'import_validating' => __('Validating import file...', 'spiralengine'),
                'import_processing' => __('Processing import...', 'spiralengine'),
                'import_complete' => __('Import complete!', 'spiralengine'),
                'error' => __('An error occurred. Please try again.', 'spiralengine'),
                'confirm_import' => __('Are you sure you want to import this data? This may overwrite existing data.', 'spiralengine'),
                'processing_chunk' => __('Processing chunk {current} of {total}...', 'spiralengine')
            ),
            'chunk_size' => 100
        ));
    }
    
    /**
     * Get inline JavaScript
     *
     * @return string
     */
    private function get_inline_script() {
        return "
        jQuery(document).ready(function($) {
            // Export form handling
            $('#export-form').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var submitBtn = form.find('input[type=\"submit\"]');
                var progressBar = $('#export-progress');
                var type = form.find('select[name=\"export_type\"]').val();
                var format = form.find('select[name=\"export_format\"]').val();
                
                // Show preview first
                $.ajax({
                    url: spiralengine_import_export.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_export_preview',
                        type: type,
                        format: format,
                        filters: form.serialize(),
                        nonce: spiralengine_import_export.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#export-preview').html(response.data.preview).show();
                            
                            // Show export button
                            $('#confirm-export').show();
                        }
                    }
                });
            });
            
            // Confirm export
            $('#confirm-export').on('click', function() {
                var form = $('#export-form');
                form.append('<input type=\"hidden\" name=\"confirmed\" value=\"1\" />');
                
                // Show progress
                $('#export-progress-container').show();
                $('#export-progress').progressbar({ value: 0 });
                
                // Submit form for download
                form.off('submit').submit();
                
                // Monitor progress
                var progressInterval = setInterval(function() {
                    $.ajax({
                        url: spiralengine_import_export.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'spiralengine_get_export_progress',
                            nonce: spiralengine_import_export.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#export-progress').progressbar('value', response.data.progress);
                                $('#export-status').text(response.data.status);
                                
                                if (response.data.progress >= 100) {
                                    clearInterval(progressInterval);
                                    $('#export-status').text(spiralengine_import_export.strings.export_complete);
                                }
                            }
                        }
                    });
                }, 1000);
            });
            
            // Export type change
            $('select[name=\"export_type\"]').on('change', function() {
                var type = $(this).val();
                var formatSelect = $('select[name=\"export_format\"]');
                
                // Update available formats
                formatSelect.empty();
                
                if (exportTypes[type] && exportTypes[type].formats) {
                    $.each(exportTypes[type].formats, function(i, format) {
                        formatSelect.append('<option value=\"' + format + '\">' + format.toUpperCase() + '</option>');
                    });
                }
                
                // Show/hide filters
                $('.export-filters').hide();
                $('#' + type + '-filters').show();
            });
            
            // Import file selection
            $('#import-file').on('change', function() {
                var file = this.files[0];
                if (!file) return;
                
                var formData = new FormData();
                formData.append('action', 'spiralengine_validate_import');
                formData.append('import_file', file);
                formData.append('import_type', $('select[name=\"import_type\"]').val());
                formData.append('nonce', spiralengine_import_export.nonce);
                
                $('#import-validation').show().text(spiralengine_import_export.strings.import_validating);
                
                $.ajax({
                    url: spiralengine_import_export.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#import-validation').html(response.data.message).removeClass('error').addClass('success');
                            $('#import-preview').html(response.data.preview).show();
                            $('#start-import').prop('disabled', false).show();
                        } else {
                            $('#import-validation').html(response.data.message).removeClass('success').addClass('error');
                            $('#start-import').prop('disabled', true);
                        }
                    }
                });
            });
            
            // Start import
            $('#start-import').on('click', function() {
                if (!confirm(spiralengine_import_export.strings.confirm_import)) {
                    return;
                }
                
                var file = $('#import-file')[0].files[0];
                var importType = $('select[name=\"import_type\"]').val();
                var totalChunks = parseInt($('#import-preview').data('chunks'));
                var currentChunk = 0;
                
                $('#import-progress-container').show();
                $('#import-progress').progressbar({ value: 0 });
                
                function processChunk() {
                    var formData = new FormData();
                    formData.append('action', 'spiralengine_process_import_chunk');
                    formData.append('import_file', file);
                    formData.append('import_type', importType);
                    formData.append('chunk', currentChunk);
                    formData.append('total_chunks', totalChunks);
                    formData.append('nonce', spiralengine_import_export.nonce);
                    
                    // Add import options
                    $('#import-form').find('input[type=\"checkbox\"]:checked').each(function() {
                        formData.append($(this).attr('name'), $(this).val());
                    });
                    
                    $('#import-status').text(
                        spiralengine_import_export.strings.processing_chunk
                            .replace('{current}', currentChunk + 1)
                            .replace('{total}', totalChunks)
                    );
                    
                    $.ajax({
                        url: spiralengine_import_export.ajax_url,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                currentChunk++;
                                var progress = (currentChunk / totalChunks) * 100;
                                $('#import-progress').progressbar('value', progress);
                                
                                if (currentChunk < totalChunks) {
                                    processChunk();
                                } else {
                                    $('#import-status').text(spiralengine_import_export.strings.import_complete);
                                    $('#import-results').html(response.data.summary).show();
                                }
                            } else {
                                $('#import-status').text(response.data.message).addClass('error');
                            }
                        },
                        error: function() {
                            $('#import-status').text(spiralengine_import_export.strings.error).addClass('error');
                        }
                    });
                }
                
                processChunk();
            });
            
            // Date range picker for exports
            $('.date-range-picker').each(function() {
                var picker = $(this);
                var startDate = picker.find('.start-date');
                var endDate = picker.find('.end-date');
                
                startDate.datepicker({
                    dateFormat: 'yy-mm-dd',
                    onSelect: function(date) {
                        endDate.datepicker('option', 'minDate', date);
                    }
                });
                
                endDate.datepicker({
                    dateFormat: 'yy-mm-dd',
                    onSelect: function(date) {
                        startDate.datepicker('option', 'maxDate', date);
                    }
                });
            });
            
            // Quick date ranges
            $('.quick-date-range').on('click', function(e) {
                e.preventDefault();
                var range = $(this).data('range');
                var today = new Date();
                var startDate, endDate;
                
                switch(range) {
                    case 'today':
                        startDate = endDate = today;
                        break;
                    case 'yesterday':
                        startDate = endDate = new Date(today.setDate(today.getDate() - 1));
                        break;
                    case 'last7days':
                        endDate = new Date();
                        startDate = new Date(today.setDate(today.getDate() - 7));
                        break;
                    case 'last30days':
                        endDate = new Date();
                        startDate = new Date(today.setDate(today.getDate() - 30));
                        break;
                    case 'thismonth':
                        startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                        endDate = new Date();
                        break;
                    case 'lastmonth':
                        startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                        break;
                }
                
                $('.start-date').datepicker('setDate', startDate);
                $('.end-date').datepicker('setDate', endDate);
            });
            
            // Scheduled exports
            $('#add-scheduled-export').on('click', function() {
                var template = $('#scheduled-export-template').html();
                $('#scheduled-exports-list').append(template);
            });
            
            // Remove scheduled export
            $(document).on('click', '.remove-scheduled-export', function() {
                $(this).closest('tr').remove();
            });
            
            // Store export types for format updates
            var exportTypes = " . json_encode($this->export_types) . ";
        });
        ";
    }
    
    /**
     * Render import/export page
     */
    public function render_page() {
        if (!current_user_can('spiralengine_import_export')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'spiralengine'));
        }
        
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'export';
        
        ?>
        <div class="wrap spiralengine-import-export">
            <h1>
                <span class="dashicons dashicons-migrate"></span>
                <?php _e('Import/Export', 'spiralengine'); ?>
            </h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=spiralengine-import-export&tab=export" 
                   class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Export', 'spiralengine'); ?>
                </a>
                <a href="?page=spiralengine-import-export&tab=import" 
                   class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Import', 'spiralengine'); ?>
                </a>
                <a href="?page=spiralengine-import-export&tab=scheduled" 
                   class="nav-tab <?php echo $active_tab === 'scheduled' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Scheduled Exports', 'spiralengine'); ?>
                </a>
                <a href="?page=spiralengine-import-export&tab=history" 
                   class="nav-tab <?php echo $active_tab === 'history' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('History', 'spiralengine'); ?>
                </a>
            </nav>
            
            <div class="import-export-content">
                <?php
                switch ($active_tab) {
                    case 'export':
                        $this->render_export_tab();
                        break;
                    case 'import':
                        $this->render_import_tab();
                        break;
                    case 'scheduled':
                        $this->render_scheduled_tab();
                        break;
                    case 'history':
                        $this->render_history_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render export tab
     */
    private function render_export_tab() {
        ?>
        <div class="export-section">
            <h2><?php _e('Export Data', 'spiralengine'); ?></h2>
            
            <form id="export-form" method="post" action="">
                <?php wp_nonce_field('spiralengine_export'); ?>
                <input type="hidden" name="action" value="spiralengine_export" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="export_type"><?php _e('Export Type', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <select name="export_type" id="export_type" class="regular-text">
                                <?php foreach ($this->export_types as $type => $config) : ?>
                                    <?php if (current_user_can($config['capability'])) : ?>
                                        <option value="<?php echo esc_attr($type); ?>">
                                            <?php echo esc_html($config['label']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php echo esc_html($this->export_types[array_key_first($this->export_types)]['description']); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="export_format"><?php _e('Format', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <select name="export_format" id="export_format">
                                <?php
                                $first_type = array_key_first($this->export_types);
                                foreach ($this->export_types[$first_type]['formats'] as $format) :
                                    ?>
                                    <option value="<?php echo esc_attr($format); ?>">
                                        <?php echo strtoupper($format); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <!-- Export Filters -->
                <div class="export-filters" id="user_data-filters">
                    <h3><?php _e('User Data Filters', 'spiralengine'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Date Range', 'spiralengine'); ?></th>
                            <td>
                                <div class="date-range-picker">
                                    <input type="date" name="start_date" class="start-date" />
                                    <span><?php _e('to', 'spiralengine'); ?></span>
                                    <input type="date" name="end_date" class="end-date" />
                                </div>
                                <p>
                                    <a href="#" class="quick-date-range" data-range="today"><?php _e('Today', 'spiralengine'); ?></a> |
                                    <a href="#" class="quick-date-range" data-range="yesterday"><?php _e('Yesterday', 'spiralengine'); ?></a> |
                                    <a href="#" class="quick-date-range" data-range="last7days"><?php _e('Last 7 Days', 'spiralengine'); ?></a> |
                                    <a href="#" class="quick-date-range" data-range="last30days"><?php _e('Last 30 Days', 'spiralengine'); ?></a> |
                                    <a href="#" class="quick-date-range" data-range="thismonth"><?php _e('This Month', 'spiralengine'); ?></a> |
                                    <a href="#" class="quick-date-range" data-range="lastmonth"><?php _e('Last Month', 'spiralengine'); ?></a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('User Filter', 'spiralengine'); ?></th>
                            <td>
                                <select name="user_filter">
                                    <option value="all"><?php _e('All Users', 'spiralengine'); ?></option>
                                    <option value="active"><?php _e('Active Users', 'spiralengine'); ?></option>
                                    <option value="specific"><?php _e('Specific User', 'spiralengine'); ?></option>
                                </select>
                                
                                <input type="text" name="specific_user" placeholder="<?php esc_attr_e('User ID or email', 'spiralengine'); ?>" style="display: none;" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Membership Tier', 'spiralengine'); ?></th>
                            <td>
                                <select name="tier_filter">
                                    <option value="all"><?php _e('All Tiers', 'spiralengine'); ?></option>
                                    <option value="free"><?php _e('Free', 'spiralengine'); ?></option>
                                    <option value="bronze"><?php _e('Bronze', 'spiralengine'); ?></option>
                                    <option value="silver"><?php _e('Silver', 'spiralengine'); ?></option>
                                    <option value="gold"><?php _e('Gold', 'spiralengine'); ?></option>
                                    <option value="platinum"><?php _e('Platinum', 'spiralengine'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Include', 'spiralengine'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="include_episodes" value="1" checked />
                                    <?php _e('Episodes', 'spiralengine'); ?>
                                </label><br />
                                
                                <label>
                                    <input type="checkbox" name="include_goals" value="1" checked />
                                    <?php _e('Goals', 'spiralengine'); ?>
                                </label><br />
                                
                                <label>
                                    <input type="checkbox" name="include_preferences" value="1" checked />
                                    <?php _e('User Preferences', 'spiralengine'); ?>
                                </label><br />
                                
                                <label>
                                    <input type="checkbox" name="include_metadata" value="1" />
                                    <?php _e('Metadata', 'spiralengine'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Privacy', 'spiralengine'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="anonymize_data" value="1" />
                                    <?php _e('Anonymize personal data', 'spiralengine'); ?>
                                </label>
                                <p class="description"><?php _e('Remove personally identifiable information', 'spiralengine'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="export-filters" id="episodes-filters" style="display: none;">
                    <h3><?php _e('Episode Filters', 'spiralengine'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Widget Type', 'spiralengine'); ?></th>
                            <td>
                                <select name="widget_filter">
                                    <option value="all"><?php _e('All Widgets', 'spiralengine'); ?></option>
                                    <?php
                                    $widgets = array(
                                        'mood_tracker' => __('Mood Tracker', 'spiralengine'),
                                        'thought_diary' => __('Thought Diary', 'spiralengine'),
                                        'gratitude_journal' => __('Gratitude Journal', 'spiralengine'),
                                        'meditation_timer' => __('Meditation Timer', 'spiralengine')
                                    );
                                    
                                    foreach ($widgets as $widget_id => $widget_name) :
                                        ?>
                                        <option value="<?php echo esc_attr($widget_id); ?>">
                                            <?php echo esc_html($widget_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Severity Range', 'spiralengine'); ?></th>
                            <td>
                                <input type="number" name="severity_min" min="1" max="10" placeholder="<?php esc_attr_e('Min', 'spiralengine'); ?>" class="small-text" />
                                <span><?php _e('to', 'spiralengine'); ?></span>
                                <input type="number" name="severity_max" min="1" max="10" placeholder="<?php esc_attr_e('Max', 'spiralengine'); ?>" class="small-text" />
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="export-filters" id="analytics-filters" style="display: none;">
                    <h3><?php _e('Analytics Filters', 'spiralengine'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Report Type', 'spiralengine'); ?></th>
                            <td>
                                <select name="report_type">
                                    <option value="summary"><?php _e('Summary Report', 'spiralengine'); ?></option>
                                    <option value="detailed"><?php _e('Detailed Report', 'spiralengine'); ?></option>
                                    <option value="trends"><?php _e('Trends Analysis', 'spiralengine'); ?></option>
                                    <option value="user_behavior"><?php _e('User Behavior', 'spiralengine'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Include Charts', 'spiralengine'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="include_charts" value="1" />
                                    <?php _e('Include visual charts (PDF only)', 'spiralengine'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div id="export-preview" style="display: none; margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                    <!-- Preview content loaded via AJAX -->
                </div>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Preview Export', 'spiralengine'); ?>" />
                    <button type="button" id="confirm-export" class="button button-primary" style="display: none;">
                        <?php _e('Download Export', 'spiralengine'); ?>
                    </button>
                </p>
                
                <div id="export-progress-container" style="display: none;">
                    <h3><?php _e('Export Progress', 'spiralengine'); ?></h3>
                    <div id="export-progress"></div>
                    <p id="export-status"></p>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render import tab
     */
    private function render_import_tab() {
        ?>
        <div class="import-section">
            <h2><?php _e('Import Data', 'spiralengine'); ?></h2>
            
            <div class="notice notice-warning">
                <p><?php _e('Warning: Importing data may overwrite existing information. Always backup your data before importing.', 'spiralengine'); ?></p>
            </div>
            
            <form id="import-form" method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('spiralengine_import'); ?>
                <input type="hidden" name="action" value="spiralengine_import" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="import_type"><?php _e('Import Type', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <select name="import_type" id="import_type">
                                <?php foreach ($this->import_types as $type => $config) : ?>
                                    <?php if (current_user_can($config['capability'])) : ?>
                                        <option value="<?php echo esc_attr($type); ?>">
                                            <?php echo esc_html($config['label']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php echo esc_html($this->import_types[array_key_first($this->import_types)]['description']); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="import_file"><?php _e('Select File', 'spiralengine'); ?></label>
                        </th>
                        <td>
                            <input type="file" name="import_file" id="import_file" accept=".csv,.json,.xml,.zip" />
                            <p class="description">
                                <?php _e('Supported formats:', 'spiralengine'); ?>
                                <?php
                                $first_type = array_key_first($this->import_types);
                                echo implode(', ', array_map('strtoupper', $this->import_types[$first_type]['formats']));
                                ?>
                            </p>
                            
                            <div id="import-validation" style="display: none; margin-top: 10px;"></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Import Options', 'spiralengine'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="skip_existing" value="1" checked />
                                <?php _e('Skip existing records', 'spiralengine'); ?>
                            </label>
                            <p class="description"><?php _e('Skip importing records that already exist', 'spiralengine'); ?></p>
                            
                            <label>
                                <input type="checkbox" name="update_existing" value="1" />
                                <?php _e('Update existing records', 'spiralengine'); ?>
                            </label>
                            <p class="description"><?php _e('Update records if they already exist', 'spiralengine'); ?></p>
                            
                            <label>
                                <input type="checkbox" name="dry_run" value="1" />
                                <?php _e('Dry run (preview only)', 'spiralengine'); ?>
                            </label>
                            <p class="description"><?php _e('Simulate import without making changes', 'spiralengine'); ?></p>
                        </td>
                    </tr>
                    
                    <tr id="user-mapping" style="display: none;">
                        <th scope="row"><?php _e('User Mapping', 'spiralengine'); ?></th>
                        <td>
                            <select name="user_mapping">
                                <option value="email"><?php _e('Match by email', 'spiralengine'); ?></option>
                                <option value="username"><?php _e('Match by username', 'spiralengine'); ?></option>
                                <option value="id"><?php _e('Match by user ID', 'spiralengine'); ?></option>
                                <option value="create"><?php _e('Create new users', 'spiralengine'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <div id="import-preview" style="display: none; margin: 20px 0;">
                    <!-- Import preview loaded via AJAX -->
                </div>
                
                <p class="submit">
                    <button type="button" id="start-import" class="button button-primary" disabled style="display: none;">
                        <?php _e('Start Import', 'spiralengine'); ?>
                    </button>
                </p>
                
                <div id="import-progress-container" style="display: none;">
                    <h3><?php _e('Import Progress', 'spiralengine'); ?></h3>
                    <div id="import-progress"></div>
                    <p id="import-status"></p>
                    
                    <div id="import-results" style="display: none; margin-top: 20px;">
                        <!-- Import results -->
                    </div>
                </div>
            </form>
            
            <div class="import-help">
                <h3><?php _e('Import File Format Help', 'spiralengine'); ?></h3>
                
                <h4><?php _e('CSV Format', 'spiralengine'); ?></h4>
                <p><?php _e('For user data imports, use the following columns:', 'spiralengine'); ?></p>
                <pre>user_email,episode_date,widget_type,severity,data</pre>
                
                <h4><?php _e('JSON Format', 'spiralengine'); ?></h4>
                <pre>{
  "users": [
    {
      "email": "user@example.com",
      "episodes": [
        {
          "date": "2024-01-01",
          "widget": "mood_tracker",
          "severity": 5,
          "data": {}
        }
      ]
    }
  ]
}</pre>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=spiralengine-import-export&download=sample'); ?>" class="button button-secondary">
                        <?php _e('Download Sample Files', 'spiralengine'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render scheduled exports tab
     */
    private function render_scheduled_tab() {
        $scheduled_exports = get_option('spiralengine_scheduled_exports', array());
        ?>
        <div class="scheduled-exports-section">
            <h2><?php _e('Scheduled Exports', 'spiralengine'); ?></h2>
            
            <p><?php _e('Set up automatic exports to run on a schedule.', 'spiralengine'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('spiralengine_scheduled_exports'); ?>
                <input type="hidden" name="action" value="save_scheduled_exports" />
                
                <table class="widefat striped" id="scheduled-exports-table">
                    <thead>
                        <tr>
                            <th><?php _e('Export Type', 'spiralengine'); ?></th>
                            <th><?php _e('Format', 'spiralengine'); ?></th>
                            <th><?php _e('Schedule', 'spiralengine'); ?></th>
                            <th><?php _e('Recipients', 'spiralengine'); ?></th>
                            <th><?php _e('Next Run', 'spiralengine'); ?></th>
                            <th><?php _e('Actions', 'spiralengine'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="scheduled-exports-list">
                        <?php if (!empty($scheduled_exports)) : ?>
                            <?php foreach ($scheduled_exports as $index => $export) : ?>
                                <tr>
                                    <td>
                                        <select name="scheduled_exports[<?php echo $index; ?>][type]">
                                            <?php foreach ($this->export_types as $type => $config) : ?>
                                                <option value="<?php echo esc_attr($type); ?>" <?php selected($export['type'], $type); ?>>
                                                    <?php echo esc_html($config['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="scheduled_exports[<?php echo $index; ?>][format]">
                                            <option value="csv" <?php selected($export['format'], 'csv'); ?>>CSV</option>
                                            <option value="json" <?php selected($export['format'], 'json'); ?>>JSON</option>
                                            <option value="pdf" <?php selected($export['format'], 'pdf'); ?>>PDF</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="scheduled_exports[<?php echo $index; ?>][schedule]">
                                            <option value="daily" <?php selected($export['schedule'], 'daily'); ?>>
                                                <?php _e('Daily', 'spiralengine'); ?>
                                            </option>
                                            <option value="weekly" <?php selected($export['schedule'], 'weekly'); ?>>
                                                <?php _e('Weekly', 'spiralengine'); ?>
                                            </option>
                                            <option value="monthly" <?php selected($export['schedule'], 'monthly'); ?>>
                                                <?php _e('Monthly', 'spiralengine'); ?>
                                            </option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="email" 
                                               name="scheduled_exports[<?php echo $index; ?>][recipients]" 
                                               value="<?php echo esc_attr($export['recipients']); ?>" 
                                               placeholder="<?php esc_attr_e('email@example.com', 'spiralengine'); ?>" />
                                    </td>
                                    <td>
                                        <?php
                                        $next_run = wp_next_scheduled('spiralengine_scheduled_export_' . $index);
                                        echo $next_run 
                                            ? human_time_diff(current_time('timestamp'), $next_run) . ' ' . __('from now', 'spiralengine')
                                            : __('Not scheduled', 'spiralengine');
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small remove-scheduled-export">
                                            <?php _e('Remove', 'spiralengine'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p>
                    <button type="button" id="add-scheduled-export" class="button button-secondary">
                        <?php _e('Add Scheduled Export', 'spiralengine'); ?>
                    </button>
                </p>
                
                <?php submit_button(__('Save Scheduled Exports', 'spiralengine')); ?>
            </form>
        </div>
        
        <!-- Template for new scheduled export row -->
        <script type="text/template" id="scheduled-export-template">
            <tr>
                <td>
                    <select name="scheduled_exports[{{INDEX}}][type]">
                        <?php foreach ($this->export_types as $type => $config) : ?>
                            <option value="<?php echo esc_attr($type); ?>">
                                <?php echo esc_html($config['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="scheduled_exports[{{INDEX}}][format]">
                        <option value="csv">CSV</option>
                        <option value="json">JSON</option>
                        <option value="pdf">PDF</option>
                    </select>
                </td>
                <td>
                    <select name="scheduled_exports[{{INDEX}}][schedule]">
                        <option value="daily"><?php _e('Daily', 'spiralengine'); ?></option>
                        <option value="weekly"><?php _e('Weekly', 'spiralengine'); ?></option>
                        <option value="monthly"><?php _e('Monthly', 'spiralengine'); ?></option>
                    </select>
                </td>
                <td>
                    <input type="email" 
                           name="scheduled_exports[{{INDEX}}][recipients]" 
                           placeholder="<?php esc_attr_e('email@example.com', 'spiralengine'); ?>" />
                </td>
                <td><?php _e('Not scheduled', 'spiralengine'); ?></td>
                <td>
                    <button type="button" class="button button-small remove-scheduled-export">
                        <?php _e('Remove', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
        </script>
        <?php
    }
    
    /**
     * Render history tab
     */
    private function render_history_tab() {
        global $wpdb;
        
        // Get export/import history
        $history = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}spiralengine_import_export_log 
            ORDER BY created_at DESC 
            LIMIT 50"
        );
        ?>
        <div class="history-section">
            <h2><?php _e('Import/Export History', 'spiralengine'); ?></h2>
            
            <?php if ($history) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'spiralengine'); ?></th>
                            <th><?php _e('Type', 'spiralengine'); ?></th>
                            <th><?php _e('Action', 'spiralengine'); ?></th>
                            <th><?php _e('Format', 'spiralengine'); ?></th>
                            <th><?php _e('Records', 'spiralengine'); ?></th>
                            <th><?php _e('Status', 'spiralengine'); ?></th>
                            <th><?php _e('User', 'spiralengine'); ?></th>
                            <th><?php _e('File', 'spiralengine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry) : ?>
                            <tr>
                                <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->created_at)); ?></td>
                                <td><?php echo esc_html($entry->type); ?></td>
                                <td>
                                    <span class="dashicons dashicons-<?php echo $entry->action === 'export' ? 'upload' : 'download'; ?>"></span>
                                    <?php echo ucfirst($entry->action); ?>
                                </td>
                                <td><?php echo strtoupper($entry->format); ?></td>
                                <td><?php echo number_format($entry->record_count); ?></td>
                                <td>
                                    <?php if ($entry->status === 'completed') : ?>
                                        <span style="color: green;">✓ <?php _e('Completed', 'spiralengine'); ?></span>
                                    <?php elseif ($entry->status === 'failed') : ?>
                                        <span style="color: red;">✗ <?php _e('Failed', 'spiralengine'); ?></span>
                                    <?php else : ?>
                                        <span style="color: orange;">⟳ <?php _e('Processing', 'spiralengine'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $user = get_user_by('id', $entry->user_id);
                                    echo $user ? esc_html($user->display_name) : __('Unknown', 'spiralengine');
                                    ?>
                                </td>
                                <td>
                                    <?php if ($entry->file_path && file_exists($entry->file_path)) : ?>
                                        <a href="<?php echo esc_url($entry->file_url); ?>" class="button button-small">
                                            <?php _e('Download', 'spiralengine'); ?>
                                        </a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p>
                    <button type="button" class="button button-secondary" id="clear-history">
                        <?php _e('Clear History', 'spiralengine'); ?>
                    </button>
                </p>
            <?php else : ?>
                <p><?php _e('No import/export history found.', 'spiralengine'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Process export
     */
    public function process_export() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'spiralengine_export') {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'spiralengine_export')) {
            wp_die(__('Security check failed', 'spiralengine'));
        }
        
        $export_type = sanitize_key($_POST['export_type']);
        $export_format = sanitize_key($_POST['export_format']);
        
        if (!isset($this->export_types[$export_type])) {
            wp_die(__('Invalid export type', 'spiralengine'));
        }
        
        if (!current_user_can($this->export_types[$export_type]['capability'])) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        // Only process if confirmed
        if (empty($_POST['confirmed'])) {
            return;
        }
        
        // Set up progress tracking
        set_transient('spiralengine_export_progress_' . get_current_user_id(), array(
            'progress' => 0,
            'status' => __('Starting export...', 'spiralengine')
        ), HOUR_IN_SECONDS);
        
        // Generate export
        $exporter_class = 'SpiralEngine_Export_' . ucfirst($export_type);
        
        if (!class_exists($exporter_class)) {
            // Use generic exporter
            $exporter = new SpiralEngine_Export_Generic($export_type, $export_format);
        } else {
            $exporter = new $exporter_class($export_format);
        }
        
        $filters = array();
        foreach ($_POST as $key => $value) {
            if (!in_array($key, array('action', '_wpnonce', 'export_type', 'export_format', 'confirmed'))) {
                $filters[$key] = sanitize_text_field($value);
            }
        }
        
        $file_path = $exporter->export($filters);
        
        if ($file_path && file_exists($file_path)) {
            // Log export
            $this->log_activity('export', $export_type, $export_format, $exporter->get_record_count(), 'completed', $file_path);
            
            // Update progress
            set_transient('spiralengine_export_progress_' . get_current_user_id(), array(
                'progress' => 100,
                'status' => __('Export complete!', 'spiralengine')
            ), HOUR_IN_SECONDS);
            
            // Send file for download
            $this->send_download($file_path, $export_format);
        } else {
            wp_die(__('Export failed', 'spiralengine'));
        }
    }
    
    /**
     * Process import
     */
    public function process_import() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'spiralengine_import') {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'spiralengine_import')) {
            wp_die(__('Security check failed', 'spiralengine'));
        }
        
        $import_type = sanitize_key($_POST['import_type']);
        
        if (!isset($this->import_types[$import_type])) {
            wp_die(__('Invalid import type', 'spiralengine'));
        }
        
        if (!current_user_can($this->import_types[$import_type]['capability'])) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('File upload failed', 'spiralengine'));
        }
        
        // This would be handled via AJAX in chunks
        // See ajax_process_import_chunk()
    }
    
    /**
     * AJAX: Export preview
     */
    public function ajax_export_preview() {
        check_ajax_referer('spiralengine_import_export', 'nonce');
        
        $export_type = sanitize_key($_POST['type']);
        $export_format = sanitize_key($_POST['format']);
        
        if (!isset($this->export_types[$export_type])) {
            wp_send_json_error(array('message' => __('Invalid export type', 'spiralengine')));
        }
        
        if (!current_user_can($this->export_types[$export_type]['capability'])) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'spiralengine')));
        }
        
        // Parse filters
        parse_str($_POST['filters'], $filters);
        
        // Get preview data
        $preview_data = $this->get_export_preview($export_type, $filters);
        
        ob_start();
        ?>
        <h3><?php _e('Export Preview', 'spiralengine'); ?></h3>
        <p>
            <?php
            printf(
                __('This export will include approximately %d records.', 'spiralengine'),
                $preview_data['record_count']
            );
            ?>
        </p>
        
        <?php if (!empty($preview_data['summary'])) : ?>
            <h4><?php _e('Summary', 'spiralengine'); ?></h4>
            <ul>
                <?php foreach ($preview_data['summary'] as $item) : ?>
                    <li><?php echo esc_html($item); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if (!empty($preview_data['sample_data'])) : ?>
            <h4><?php _e('Sample Data', 'spiralengine'); ?></h4>
            <pre style="background: #f0f0f0; padding: 10px; overflow: auto; max-height: 200px;">
<?php echo esc_html(print_r($preview_data['sample_data'], true)); ?>
            </pre>
        <?php endif; ?>
        
        <p>
            <strong><?php _e('Estimated file size:', 'spiralengine'); ?></strong>
            <?php echo size_format($preview_data['estimated_size']); ?>
        </p>
        <?php
        $preview_html = ob_get_clean();
        
        wp_send_json_success(array('preview' => $preview_html));
    }
    
    /**
     * AJAX: Validate import file
     */
    public function ajax_validate_import() {
        check_ajax_referer('spiralengine_import_export', 'nonce');
        
        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'spiralengine')));
        }
        
        $import_type = sanitize_key($_POST['import_type']);
        
        if (!isset($this->import_types[$import_type])) {
            wp_send_json_error(array('message' => __('Invalid import type', 'spiralengine')));
        }
        
        if (!current_user_can($this->import_types[$import_type]['capability'])) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'spiralengine')));
        }
        
        $file = $_FILES['import_file'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($file_extension, $this->import_types[$import_type]['formats'])) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Invalid file format. Expected: %s', 'spiralengine'),
                    implode(', ', $this->import_types[$import_type]['formats'])
                )
            ));
        }
        
        // Parse and validate file
        $validation_result = $this->validate_import_file($file['tmp_name'], $import_type, $file_extension);
        
        if ($validation_result['valid']) {
            $preview_html = $this->generate_import_preview($validation_result);
            
            wp_send_json_success(array(
                'message' => __('File validated successfully', 'spiralengine'),
                'preview' => $preview_html
            ));
        } else {
            wp_send_json_error(array(
                'message' => $validation_result['error']
            ));
        }
    }
    
    /**
     * AJAX: Process import chunk
     */
    public function ajax_process_import_chunk() {
        check_ajax_referer('spiralengine_import_export', 'nonce');
        
        if (!isset($_FILES['import_file']) && empty($_POST['file_id'])) {
            wp_send_json_error(array('message' => __('No file provided', 'spiralengine')));
        }
        
        $import_type = sanitize_key($_POST['import_type']);
        $chunk = intval($_POST['chunk']);
        $total_chunks = intval($_POST['total_chunks']);
        
        // Process chunk
        $importer_class = 'SpiralEngine_Import_' . ucfirst($import_type);
        
        if (!class_exists($importer_class)) {
            $importer = new SpiralEngine_Import_Generic($import_type);
        } else {
            $importer = new $importer_class();
        }
        
        $options = array(
            'skip_existing' => !empty($_POST['skip_existing']),
            'update_existing' => !empty($_POST['update_existing']),
            'dry_run' => !empty($_POST['dry_run'])
        );
        
        $result = $importer->process_chunk($chunk, $options);
        
        if ($chunk === $total_chunks - 1) {
            // Last chunk - finalize import
            $summary = $importer->get_summary();
            
            $this->log_activity(
                'import',
                $import_type,
                pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION),
                $summary['total_records'],
                'completed'
            );
            
            wp_send_json_success(array(
                'complete' => true,
                'summary' => $this->format_import_summary($summary)
            ));
        } else {
            wp_send_json_success(array(
                'complete' => false,
                'processed' => $result['processed']
            ));
        }
    }
    
    /**
     * AJAX: Get export progress
     */
    public function ajax_get_export_progress() {
        check_ajax_referer('spiralengine_import_export', 'nonce');
        
        $progress = get_transient('spiralengine_export_progress_' . get_current_user_id());
        
        if ($progress) {
            wp_send_json_success($progress);
        } else {
            wp_send_json_success(array(
                'progress' => 0,
                'status' => __('Waiting...', 'spiralengine')
            ));
        }
    }
    
    /**
     * Get export preview data
     *
     * @param string $export_type
     * @param array $filters
     * @return array
     */
    private function get_export_preview($export_type, $filters) {
        global $wpdb;
        
        $preview_data = array(
            'record_count' => 0,
            'summary' => array(),
            'sample_data' => array(),
            'estimated_size' => 0
        );
        
        switch ($export_type) {
            case 'user_data':
                // Count records based on filters
                $query = "SELECT COUNT(DISTINCT e.user_id) FROM {$wpdb->prefix}spiralengine_episodes e";
                $where = array();
                
                if (!empty($filters['start_date'])) {
                    $where[] = $wpdb->prepare("e.created_at >= %s", $filters['start_date']);
                }
                if (!empty($filters['end_date'])) {
                    $where[] = $wpdb->prepare("e.created_at <= %s", $filters['end_date'] . ' 23:59:59');
                }
                
                if (!empty($where)) {
                    $query .= " WHERE " . implode(' AND ', $where);
                }
                
                $user_count = $wpdb->get_var($query);
                $episode_count = $wpdb->get_var(str_replace('COUNT(DISTINCT e.user_id)', 'COUNT(*)', $query));
                
                $preview_data['record_count'] = $episode_count;
                $preview_data['summary'] = array(
                    sprintf(__('%d users', 'spiralengine'), $user_count),
                    sprintf(__('%d episodes', 'spiralengine'), $episode_count)
                );
                
                // Get sample data
                $sample = $wpdb->get_results($query . " LIMIT 3");
                $preview_data['sample_data'] = $sample;
                
                // Estimate size
                $preview_data['estimated_size'] = $episode_count * 500; // Rough estimate
                break;
                
            case 'analytics':
                $preview_data['record_count'] = 1;
                $preview_data['summary'] = array(
                    __('Complete analytics report', 'spiralengine'),
                    __('All metrics and charts', 'spiralengine')
                );
                $preview_data['estimated_size'] = 1024 * 1024; // 1MB estimate
                break;
        }
        
        return $preview_data;
    }
    
    /**
     * Validate import file
     *
     * @param string $file_path
     * @param string $import_type
     * @param string $format
     * @return array
     */
    private function validate_import_file($file_path, $import_type, $format) {
        $result = array(
            'valid' => false,
            'error' => '',
            'data' => array()
        );
        
        switch ($format) {
            case 'csv':
                $handle = fopen($file_path, 'r');
                if (!$handle) {
                    $result['error'] = __('Could not open file', 'spiralengine');
                    return $result;
                }
                
                // Read headers
                $headers = fgetcsv($handle);
                
                // Validate headers based on import type
                $required_headers = $this->get_required_headers($import_type);
                $missing_headers = array_diff($required_headers, $headers);
                
                if (!empty($missing_headers)) {
                    $result['error'] = sprintf(
                        __('Missing required columns: %s', 'spiralengine'),
                        implode(', ', $missing_headers)
                    );
                    fclose($handle);
                    return $result;
                }
                
                // Count rows
                $row_count = 0;
                $sample_data = array();
                while (($data = fgetcsv($handle)) !== false) {
                    $row_count++;
                    if ($row_count <= 5) {
                        $sample_data[] = array_combine($headers, $data);
                    }
                }
                
                fclose($handle);
                
                $result['valid'] = true;
                $result['data'] = array(
                    'headers' => $headers,
                    'row_count' => $row_count,
                    'sample_data' => $sample_data,
                    'chunks' => ceil($row_count / 100)
                );
                break;
                
            case 'json':
                $content = file_get_contents($file_path);
                $data = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $result['error'] = __('Invalid JSON format', 'spiralengine');
                    return $result;
                }
                
                // Validate structure
                $result['valid'] = true;
                $result['data'] = array(
                    'record_count' => count($data),
                    'sample_data' => array_slice($data, 0, 5),
                    'chunks' => ceil(count($data) / 100)
                );
                break;
        }
        
        return $result;
    }
    
    /**
     * Get required headers for import type
     *
     * @param string $import_type
     * @return array
     */
    private function get_required_headers($import_type) {
        $headers = array(
            'user_data' => array('user_email', 'episode_date', 'widget_type'),
            'settings' => array('setting_key', 'setting_value'),
            'widgets' => array('widget_id', 'widget_settings')
        );
        
        return $headers[$import_type] ?? array();
    }
    
    /**
     * Generate import preview HTML
     *
     * @param array $validation_result
     * @return string
     */
    private function generate_import_preview($validation_result) {
        ob_start();
        ?>
        <div class="import-preview-content" data-chunks="<?php echo $validation_result['data']['chunks']; ?>">
            <h3><?php _e('Import Preview', 'spiralengine'); ?></h3>
            
            <p>
                <?php
                printf(
                    __('Found %d records to import.', 'spiralengine'),
                    $validation_result['data']['row_count'] ?? $validation_result['data']['record_count']
                );
                ?>
            </p>
            
            <?php if (!empty($validation_result['data']['sample_data'])) : ?>
                <h4><?php _e('Sample Data', 'spiralengine'); ?></h4>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <?php
                            $headers = array_keys($validation_result['data']['sample_data'][0]);
                            foreach ($headers as $header) :
                                ?>
                                <th><?php echo esc_html($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($validation_result['data']['sample_data'] as $row) : ?>
                            <tr>
                                <?php foreach ($row as $value) : ?>
                                    <td><?php echo esc_html($value); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($validation_result['data']['row_count'] > 5) : ?>
                    <p><em><?php _e('... and more rows', 'spiralengine'); ?></em></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Format import summary
     *
     * @param array $summary
     * @return string
     */
    private function format_import_summary($summary) {
        ob_start();
        ?>
        <div class="import-summary">
            <h3><?php _e('Import Summary', 'spiralengine'); ?></h3>
            
            <table class="widefat">
                <tr>
                    <th><?php _e('Total Records', 'spiralengine'); ?></th>
                    <td><?php echo number_format($summary['total_records']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Imported', 'spiralengine'); ?></th>
                    <td style="color: green;"><?php echo number_format($summary['imported']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Updated', 'spiralengine'); ?></th>
                    <td style="color: blue;"><?php echo number_format($summary['updated']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Skipped', 'spiralengine'); ?></th>
                    <td style="color: orange;"><?php echo number_format($summary['skipped']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Errors', 'spiralengine'); ?></th>
                    <td style="color: red;"><?php echo number_format($summary['errors']); ?></td>
                </tr>
            </table>
            
            <?php if (!empty($summary['error_details'])) : ?>
                <h4><?php _e('Error Details', 'spiralengine'); ?></h4>
                <ul>
                    <?php foreach ($summary['error_details'] as $error) : ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Send file for download
     *
     * @param string $file_path
     * @param string $format
     */
    private function send_download($file_path, $format) {
        $filename = basename($file_path);
        
        // Set headers
        header('Content-Type: ' . $this->get_mime_type($format));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output file
        readfile($file_path);
        
        // Clean up temp file
        unlink($file_path);
        
        exit;
    }
    
    /**
     * Get MIME type for format
     *
     * @param string $format
     * @return string
     */
    private function get_mime_type($format) {
        $mime_types = array(
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip'
        );
        
        return $mime_types[$format] ?? 'application/octet-stream';
    }
    
    /**
     * Log import/export activity
     *
     * @param string $action
     * @param string $type
     * @param string $format
     * @param int $record_count
     * @param string $status
     * @param string $file_path
     */
    private function log_activity($action, $type, $format, $record_count, $status, $file_path = '') {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_import_export_log',
            array(
                'action' => $action,
                'type' => $type,
                'format' => $format,
                'record_count' => $record_count,
                'status' => $status,
                'user_id' => get_current_user_id(),
                'file_path' => $file_path,
                'file_url' => $file_path ? str_replace(ABSPATH, home_url('/'), $file_path) : '',
                'created_at' => current_time('mysql')
            )
        );
    }
}

/**
 * Generic Export Class
 */
class SpiralEngine_Export_Generic {
    private $type;
    private $format;
    private $record_count = 0;
    
    public function __construct($type, $format) {
        $this->type = $type;
        $this->format = $format;
    }
    
    public function export($filters) {
        // Implementation would vary by type and format
        // This is a simplified example
        
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/spiralengine-exports';
        
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        $filename = 'spiralengine-' . $this->type . '-' . date('Y-m-d-His') . '.' . $this->format;
        $file_path = $export_dir . '/' . $filename;
        
        // Get data based on type
        $data = $this->get_export_data($filters);
        
        // Write to file based on format
        switch ($this->format) {
            case 'csv':
                $this->write_csv($file_path, $data);
                break;
            case 'json':
                file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT));
                break;
        }
        
        return $file_path;
    }
    
    private function get_export_data($filters) {
        global $wpdb;
        
        // Example for user_data export
        if ($this->type === 'user_data') {
            $query = "SELECT * FROM {$wpdb->prefix}spiralengine_episodes WHERE 1=1";
            
            if (!empty($filters['start_date'])) {
                $query .= $wpdb->prepare(" AND created_at >= %s", $filters['start_date']);
            }
            
            $results = $wpdb->get_results($query, ARRAY_A);
            $this->record_count = count($results);
            
            return $results;
        }
        
        return array();
    }
    
    private function write_csv($file_path, $data) {
        $handle = fopen($file_path, 'w');
        
        if (!empty($data)) {
            // Write headers
            fputcsv($handle, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }
        
        fclose($handle);
    }
    
    public function get_record_count() {
        return $this->record_count;
    }
}

/**
 * Generic Import Class
 */
class SpiralEngine_Import_Generic {
    private $type;
    private $processed = 0;
    private $imported = 0;
    private $updated = 0;
    private $skipped = 0;
    private $errors = 0;
    private $error_details = array();
    
    public function __construct($type) {
        $this->type = $type;
    }
    
    public function process_chunk($chunk, $options) {
        // Implementation would vary by type
        // This is a simplified example
        
        return array(
            'processed' => 100,
            'imported' => 80,
            'updated' => 10,
            'skipped' => 10,
            'errors' => 0
        );
    }
    
    public function get_summary() {
        return array(
            'total_records' => $this->processed,
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'error_details' => $this->error_details
        );
    }
}


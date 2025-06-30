/**
 * Spiral Engine - Dashboard Widget JavaScript
 * 
 * @package     SpiralEngine
 * @subpackage  Assets/JS
 * @since       1.0.0
 * 
 * File: assets/js/spiralengine-dashboard-widget.js
 */

(function($) {
    'use strict';

    /**
     * Dashboard Controller
     */
    const SpiralDashboard = {
        
        // Properties
        chart: null,
        timeline: null,
        forecast: null,
        isDragging: false,
        
        /**
         * Initialize dashboard
         */
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.initTimeline();
            this.initDragDrop();
            this.loadDashboardData();
            this.initTooltips();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Customization
            $('#sp-customize-dashboard').on('click', () => this.openCustomization());
            $('#sp-close-customize').on('click', () => this.closeCustomization());
            $('#sp-save-customization').on('click', () => this.saveCustomization());
            $('#sp-reset-layout').on('click', () => this.resetLayout());
            
            // Export
            $('#sp-export-data').on('click', () => this.openExportModal());
            $('#sp-confirm-export').on('click', () => this.exportData());
            
            // Timeline filters
            $('#sp-timeline-type-filter, #sp-timeline-range').on('change', () => this.filterTimeline());
            
            // Forecast refresh
            $('.sp-refresh-forecast').on('click', () => this.refreshForecast());
            
            // Quick log
            $(document).on('click', '.sp-quick-log-btn', (e) => this.openQuickLog(e));
            $('#sp-save-quick-log').on('click', () => this.saveQuickLog());
            
            // Severity slider
            $('#sp-quick-severity').on('input', function() {
                $('#sp-severity-display').text($(this).val());
            });
            
            // Modal controls
            $('.sp-modal-close, [data-modal]').on('click', function() {
                const modalId = $(this).data('modal');
                if (modalId) {
                    $('#' + modalId).fadeOut();
                }
            });
            
            // Widget toggles
            $('.sp-widget-toggle').on('change', function() {
                const widgetId = $(this).data('widget');
                const isVisible = $(this).prop('checked');
                SpiralDashboard.toggleWidget(widgetId, isVisible);
            });
            
            // Responsive menu
            $(window).on('resize', () => this.handleResize());
        },
        
        /**
         * Initialize charts
         */
        initCharts: function() {
            // SPIRAL Score History Chart
            if ($('#sp-score-history-chart').length && typeof spiralScoreHistory !== 'undefined') {
                const ctx = document.getElementById('sp-score-history-chart').getContext('2d');
                
                this.chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: spiralScoreHistory.labels,
                        datasets: [{
                            label: 'SPIRAL Score',
                            data: spiralScoreHistory.data,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: '#667eea',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                cornerRadius: 8,
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        return 'Score: ' + context.parsed.y.toFixed(1);
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    stepSize: 20,
                                    callback: function(value) {
                                        return value;
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            }
            
            // Initialize gauge animations
            this.animateGauges();
        },
        
        /**
         * Animate gauge displays
         */
        animateGauges: function() {
            $('.sp-gauge-fill').each(function() {
                const $gauge = $(this);
                const dashOffset = $gauge.css('stroke-dashoffset');
                
                $gauge.css('stroke-dashoffset', '251.2');
                
                setTimeout(() => {
                    $gauge.css({
                        'stroke-dashoffset': dashOffset,
                        'transition': 'stroke-dashoffset 1.5s ease-out'
                    });
                }, 100);
            });
        },
        
        /**
         * Initialize episode timeline
         */
        initTimeline: function() {
            this.timeline = new EpisodeTimeline($('#sp-episode-timeline'));
        },
        
        /**
         * Initialize drag and drop
         */
        initDragDrop: function() {
            if (!$('#sp-dashboard-grid').length) return;
            
            $('#sp-dashboard-grid').sortable({
                items: '.sp-dashboard-widget',
                handle: '.sp-widget-header',
                placeholder: 'sp-widget-placeholder',
                tolerance: 'pointer',
                cursor: 'move',
                start: (e, ui) => {
                    ui.placeholder.height(ui.item.height());
                    this.isDragging = true;
                },
                stop: (e, ui) => {
                    this.isDragging = false;
                    this.saveLayout();
                }
            });
        },
        
        /**
         * Load dashboard data
         */
        loadDashboardData: function() {
            // Timeline is loaded separately
            if (this.timeline) {
                this.timeline.load();
            }
            
            // Load any other dynamic content
            this.updateStats();
        },
        
        /**
         * Update statistics
         */
        updateStats: function() {
            // Animate stat numbers
            $('.sp-stat-value').each(function() {
                const $stat = $(this);
                const value = parseInt($stat.text().replace(/,/g, ''));
                
                $stat.prop('Counter', 0).animate({
                    Counter: value
                }, {
                    duration: 1000,
                    easing: 'swing',
                    step: function(now) {
                        $stat.text(Math.ceil(now).toLocaleString());
                    }
                });
            });
        },
        
        /**
         * Open customization panel
         */
        openCustomization: function() {
            $('#sp-customize-panel').slideDown();
            $('body').addClass('sp-customizing');
        },
        
        /**
         * Close customization panel
         */
        closeCustomization: function() {
            $('#sp-customize-panel').slideUp();
            $('body').removeClass('sp-customizing');
        },
        
        /**
         * Save customization settings
         */
        saveCustomization: function() {
            const settings = {
                hidden_widgets: [],
                preferences: {}
            };
            
            // Get hidden widgets
            $('.sp-widget-toggle:not(:checked)').each(function() {
                settings.hidden_widgets.push($(this).data('widget'));
            });
            
            // Save via AJAX
            $.ajax({
                url: spiralDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'spiralengine_dashboard_save_settings',
                    settings: settings,
                    nonce: spiralDashboard.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(spiralDashboard.strings.save_success, 'success');
                        this.closeCustomization();
                    }
                },
                error: () => {
                    this.showNotification(spiralDashboard.strings.save_error, 'error');
                }
            });
        },
        
        /**
         * Save dashboard layout
         */
        saveLayout: function() {
            const layout = [];
            
            $('#sp-dashboard-grid .sp-dashboard-widget').each(function(index) {
                layout.push({
                    widget: $(this).data('widget'),
                    position: index
                });
            });
            
            $.ajax({
                url: spiralDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'spiralengine_dashboard_save_layout',
                    layout: layout,
                    nonce: spiralDashboard.nonce
                },
                success: (response) => {
                    // Silent save
                },
                error: () => {
                    console.error('Failed to save layout');
                }
            });
        },
        
        /**
         * Reset layout to default
         */
        resetLayout: function() {
            if (!confirm(spiralDashboard.strings.confirm_reset)) {
                return;
            }
            
            $.ajax({
                url: spiralDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'spiralengine_dashboard_reset_layout',
                    nonce: spiralDashboard.nonce
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        },
        
        /**
         * Toggle widget visibility
         */
        toggleWidget: function(widgetId, isVisible) {
            const $widget = $(`.sp-dashboard-widget[data-widget="${widgetId}"]`);
            
            if (isVisible) {
                $widget.fadeIn();
            } else {
                $widget.fadeOut();
            }
        },
        
        /**
         * Filter timeline
         */
        filterTimeline: function() {
            const type = $('#sp-timeline-type-filter').val();
            const range = $('#sp-timeline-range').val();
            
            if (this.timeline) {
                this.timeline.filter(type, range);
            }
        },
        
        /**
         * Refresh forecast
         */
        refreshForecast: function() {
            const $button = $('.sp-refresh-forecast');
            $button.addClass('sp-loading');
            
            $.ajax({
                url: spiralDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'spiralengine_dashboard_refresh_forecast',
                    window: '7_day',
                    nonce: spiralDashboard.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateForecastDisplay(response.data);
                    }
                },
                complete: () => {
                    $button.removeClass('sp-loading');
                }
            });
        },
        
        /**
         * Update forecast display
         */
        updateForecastDisplay: function(data) {
            // Update risk gauge
            const $gauge = $('.sp-risk-gauge');
            const angle = (data.overall_risk_score / 10) * 180;
            
            $gauge.find('.sp-gauge-fill').css('transform', `rotate(${angle}deg)`);
            $gauge.find('.sp-risk-value').text(data.overall_risk_score.toFixed(1));
            
            // Update risk breakdown
            // ... additional update logic
            
            this.showNotification('Forecast updated', 'success');
        },
        
        /**
         * Open quick log modal
         */
        openQuickLog: function(e) {
            const $button = $(e.currentTarget);
            const episodeType = $button.data('episode-type');
            
            $('#sp-quick-log-type').val(episodeType);
            $('#sp-quick-log-modal').fadeIn();
            
            // Update modal title
            const typeName = $button.closest('.sp-card-header').find('.sp-card-title').text();
            $('#sp-quick-log-modal .sp-modal-header h3').text(`Quick ${typeName}`);
        },
        
        /**
         * Save quick log
         */
        saveQuickLog: function() {
            const formData = {
                episode_type: $('#sp-quick-log-type').val(),
                severity: $('#sp-quick-severity').val(),
                thought: $('#sp-quick-thought').val()
            };
            
            $.ajax({
                url: spiralDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'spiralengine_dashboard_quick_log',
                    ...formData,
                    nonce: spiralDashboard.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#sp-quick-log-modal').fadeOut();
                        this.showNotification(spiralDashboard.strings.quick_log_success, 'success');
                        
                        // Refresh affected widgets
                        if (response.data.refresh_widgets) {
                            response.data.refresh_widgets.forEach(widget => {
                                this.refreshWidget(widget);
                            });
                        }
                        
                        // Reset form
                        $('#sp-quick-log-form')[0].reset();
                        $('#sp-severity-display').text('5');
                    }
                },
                error: () => {
                    this.showNotification('Failed to save episode', 'error');
                }
            });
        },
        
        /**
         * Refresh specific widget
         */
        refreshWidget: function(widgetId) {
            switch (widgetId) {
                case 'episode_timeline':
                    if (this.timeline) {
                        this.timeline.load();
                    }
                    break;
                case 'unified_forecast':
                    this.refreshForecast();
                    break;
                default:
                    // Reload widget content
                    break;
            }
        },
        
        /**
         * Open export modal
         */
        openExportModal: function() {
            $('#sp-export-modal').fadeIn();
        },
        
        /**
         * Export data
         */
        exportData: function() {
            const exportOptions = {
                episodes: $('input[name="export_episodes"]').prop('checked'),
                assessments: $('input[name="export_assessments"]').prop('checked'),
                correlations: $('input[name="export_correlations"]').prop('checked'),
                achievements: $('input[name="export_achievements"]').prop('checked'),
                format: $('input[name="export_format"]:checked').val()
            };
            
            $.ajax({
                url: spiralDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'spiralengine_dashboard_export_data',
                    options: exportOptions,
                    nonce: spiralDashboard.nonce
                },
                success: (response) => {
                    if (response.success && response.data.download_url) {
                        // Download file
                        window.location.href = response.data.download_url;
                        $('#sp-export-modal').fadeOut();
                        this.showNotification(spiralDashboard.strings.export_success, 'success');
                    }
                },
                error: () => {
                    this.showNotification('Export failed', 'error');
                }
            });
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Basic tooltip implementation
            $('[title]').each(function() {
                const $el = $(this);
                const title = $el.attr('title');
                
                $el.removeAttr('title').attr('data-tooltip', title);
                
                $el.on('mouseenter', function() {
                    const $tooltip = $('<div class="sp-tooltip">' + title + '</div>');
                    $('body').append($tooltip);
                    
                    const pos = $el.offset();
                    $tooltip.css({
                        top: pos.top - $tooltip.outerHeight() - 10,
                        left: pos.left + ($el.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                    }).fadeIn();
                }).on('mouseleave', function() {
                    $('.sp-tooltip').remove();
                });
            });
        },
        
        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            const $notification = $(`
                <div class="sp-notification sp-notification-${type}">
                    <span class="dashicons dashicons-${type === 'success' ? 'yes' : 'info'}"></span>
                    ${message}
                </div>
            `);
            
            $('body').append($notification);
            
            setTimeout(() => {
                $notification.addClass('sp-show');
            }, 100);
            
            setTimeout(() => {
                $notification.removeClass('sp-show');
                setTimeout(() => $notification.remove(), 300);
            }, 3000);
        },
        
        /**
         * Handle window resize
         */
        handleResize: function() {
            // Adjust layout for mobile
            if ($(window).width() < 768) {
                $('#sp-dashboard-grid').sortable('disable');
            } else {
                $('#sp-dashboard-grid').sortable('enable');
            }
        },
        
        /**
         * Get time-based greeting (helper)
         */
        getTimeBasedGreeting: function() {
            const hour = new Date().getHours();
            
            if (hour < 12) {
                return 'Good morning';
            } else if (hour < 17) {
                return 'Good afternoon';
            } else {
                return 'Good evening';
            }
        }
    };
    
    /**
     * Episode Timeline Class
     */
    class EpisodeTimeline {
        constructor($container) {
            this.$container = $container;
            this.episodes = [];
            this.filters = {
                type: 'all',
                range: 30
            };
            this.init();
        }
        
        init() {
            this.bindEvents();
        }
        
        bindEvents() {
            // Click on episodes for details
            this.$container.on('click', '.sp-timeline-episode', (e) => {
                this.showEpisodeDetails($(e.currentTarget).data('id'));
            });
        }
        
        load() {
            this.$container.html('<div class="sp-loading"><span class="sp-spinner"></span>Loading timeline...</div>');
            
            $.ajax({
                url: spiralDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'spiralengine_dashboard_get_timeline',
                    days: this.filters.range,
                    type: this.filters.type,
                    nonce: spiralDashboard.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.episodes = response.data.episodes;
                        this.correlations = response.data.correlations;
                        this.render();
                    }
                },
                error: () => {
                    this.$container.html('<p class="sp-error">Failed to load timeline</p>');
                }
            });
        }
        
        filter(type, range) {
            this.filters.type = type;
            this.filters.range = range;
            this.load();
        }
        
        render() {
            if (this.episodes.length === 0) {
                this.$container.html('<p class="sp-no-data">No episodes in this time period</p>');
                return;
            }
            
            // Group episodes by date
            const grouped = this.groupByDate(this.episodes);
            
            let html = '<div class="sp-timeline-wrapper">';
            
            for (const [date, episodes] of Object.entries(grouped)) {
                html += this.renderDay(date, episodes);
            }
            
            html += '</div>';
            
            this.$container.html(html);
            
            // Add correlation lines
            this.renderCorrelations();
        }
        
        groupByDate(episodes) {
            const grouped = {};
            
            episodes.forEach(episode => {
                const date = episode.episode_date.split(' ')[0];
                if (!grouped[date]) {
                    grouped[date] = [];
                }
                grouped[date].push(episode);
            });
            
            return grouped;
        }
        
        renderDay(date, episodes) {
            const dateObj = new Date(date);
            const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
            const monthDay = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            
            let html = `
                <div class="sp-timeline-day" data-date="${date}">
                    <div class="sp-timeline-date">
                        <span class="sp-day-name">${dayName}</span>
                        <span class="sp-month-day">${monthDay}</span>
                    </div>
                    <div class="sp-timeline-episodes">
            `;
            
            episodes.forEach(episode => {
                html += this.renderEpisode(episode);
            });
            
            html += '</div></div>';
            
            return html;
        }
        
        renderEpisode(episode) {
            const hasCorrelations = this.checkCorrelations(episode.episode_id);
            
            return `
                <div class="sp-timeline-episode" 
                     data-id="${episode.episode_id}"
                     data-type="${episode.episode_type}"
                     data-severity="${episode.severity_score}"
                     style="background-color: ${episode.color}20;">
                    <div class="sp-episode-header">
                        <span class="${episode.icon}" style="color: ${episode.color}"></span>
                        <span class="sp-episode-type">${episode.display_name}</span>
                        <span class="sp-episode-time">${this.formatTime(episode.episode_date)}</span>
                    </div>
                    <div class="sp-episode-severity">
                        <div class="sp-severity-bar" style="width: ${episode.severity_score * 10}%"></div>
                    </div>
                    ${hasCorrelations ? '<span class="sp-correlation-indicator dashicons dashicons-link"></span>' : ''}
                </div>
            `;
        }
        
        formatTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
        }
        
        checkCorrelations(episodeId) {
            return this.correlations && this.correlations.some(c => 
                c.primary_episode_id === episodeId || 
                c.related_episode_id === episodeId
            );
        }
        
        renderCorrelations() {
            // Add visual correlation lines between episodes
            // This would use SVG or canvas to draw connections
        }
        
        showEpisodeDetails(episodeId) {
            // Show episode details in a modal or expand inline
            const episode = this.episodes.find(e => e.episode_id === episodeId);
            if (!episode) return;
            
            // Implementation for showing details
        }
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        SpiralDashboard.init();
    });
    
})(jQuery);


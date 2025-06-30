// assets/js/spiralengine-overthinking-widget.js

/**
 * Overthinking Episode Logger Widget JavaScript
 * 
 * Handles:
 * - Mode switching between quick and detailed
 * - Form validation and submission
 * - Pattern checking and alerts
 * - Auto-save functionality
 * - Correlation displays
 * - Real-time UI updates
 * 
 * @package SpiralEngine
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Namespace for overthinking widget
    window.spiralEngineOverthinking = {
        
        // Configuration
        config: {
            autosaveInterval: 30000, // 30 seconds
            patternCheckDelay: 2000, // 2 seconds after input
            maxRetries: 3,
            endpoints: {
                quickLog: spiralEngine.ajaxUrl + '?action=overthinking_log_quick',
                detailedLog: spiralEngine.ajaxUrl + '?action=overthinking_log_detailed',
                checkPatterns: spiralEngine.ajaxUrl + '?action=overthinking_check_patterns',
                getCorrelations: spiralEngine.ajaxUrl + '?action=overthinking_get_correlations',
                saveDraft: spiralEngine.ajaxUrl + '?action=overthinking_save_draft'
            }
        },
        
        // State management
        state: {
            currentMode: null,
            currentSection: 1,
            totalSections: 0,
            formData: {},
            autosaveTimer: null,
            patternCheckTimer: null,
            isDirty: false
        },
        
        /**
         * Initialize the widget
         */
        init: function() {
            // Detect mode from DOM
            const $widget = $('.spiral-overthinking-quick, .spiral-overthinking-detailed');
            if ($widget.hasClass('spiral-overthinking-quick')) {
                this.initQuickLogger();
            } else if ($widget.hasClass('spiral-overthinking-detailed')) {
                this.initDetailedLogger();
            }
        },
        
        /**
         * Initialize quick logger
         */
        initQuickLogger: function() {
            this.state.currentMode = 'quick';
            
            // Bind events
            this.bindQuickEvents();
            
            // Initialize components
            this.initSliders();
            this.initCharCounter();
            
            // Load saved draft if exists
            this.loadDraft('quick');
        },
        
        /**
         * Initialize detailed logger
         */
        initDetailedLogger: function() {
            this.state.currentMode = 'detailed';
            
            // Count sections
            this.state.totalSections = $('.spiral-form-section').length;
            
            // Bind events
            this.bindDetailedEvents();
            
            // Initialize components
            this.initSliders();
            this.initCharCounter();
            this.initConditionalFields();
            this.initProgressBar();
            
            // Start autosave
            this.startAutosave();
            
            // Load saved draft if exists
            this.loadDraft('detailed');
        },
        
        /**
         * Bind events for quick logger
         */
        bindQuickEvents: function() {
            const self = this;
            
            // Form submission
            $('#spiral-overthinking-quick-form').on('submit', function(e) {
                e.preventDefault();
                self.submitQuickLog($(this));
            });
            
            // Real-time pattern checking
            $('#overthinking_primary_thought').on('input', function() {
                self.schedulePatternCheck();
            });
        },
        
        /**
         * Bind events for detailed logger
         */
        bindDetailedEvents: function() {
            const self = this;
            
            // Form submission
            $('#spiral-overthinking-detailed-form').on('submit', function(e) {
                e.preventDefault();
                self.submitDetailedLog($(this));
            });
            
            // Save draft button
            $('#spiral-save-draft').on('click', function() {
                self.saveDraft();
            });
            
            // View history button
            $('#spiral-view-history').on('click', function() {
                window.location.href = spiralEngine.siteUrl + '/my-overthinking-history/';
            });
            
            // Section navigation
            $('.spiral-step').on('click', function() {
                const section = $(this).data('section');
                self.navigateToSection(section);
            });
            
            // Form field changes
            $('#spiral-overthinking-detailed-form').on('change input', 'input, select, textarea', function() {
                self.state.isDirty = true;
                self.updateProgress();
            });
            
            // Modal controls
            $('.spiral-modal-close, #spiral-log-another').on('click', function() {
                self.closeModal();
            });
            
            // Warn before leaving with unsaved changes
            $(window).on('beforeunload', function() {
                if (self.state.isDirty) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        },
        
        /**
         * Initialize sliders
         */
        initSliders: function() {
            $('.spiral-slider').each(function() {
                const $slider = $(this);
                const $valueLabel = $slider.siblings('.spiral-slider-labels').find('.spiral-value-label');
                
                $slider.on('input', function() {
                    $valueLabel.text($(this).val());
                });
            });
        },
        
        /**
         * Initialize character counter
         */
        initCharCounter: function() {
            $('textarea[maxlength]').each(function() {
                const $textarea = $(this);
                const $counter = $textarea.siblings('.spiral-char-count').find('.spiral-char-current');
                const maxLength = parseInt($textarea.attr('maxlength'));
                
                $textarea.on('input', function() {
                    const currentLength = $(this).val().length;
                    $counter.text(currentLength);
                    
                    // Add warning class when near limit
                    if (currentLength > maxLength * 0.9) {
                        $counter.parent().addClass('spiral-char-warning');
                    } else {
                        $counter.parent().removeClass('spiral-char-warning');
                    }
                });
            });
        },
        
        /**
         * Initialize conditional fields
         */
        initConditionalFields: function() {
            const self = this;
            
            $('[data-conditional]').each(function() {
                const $field = $(this);
                const conditional = $field.data('conditional');
                const $dependentField = $('[name="overthinking_' + conditional.field + '"]');
                
                // Check initial state
                self.checkConditionalField($field, $dependentField, conditional);
                
                // Monitor changes
                $dependentField.on('change', function() {
                    self.checkConditionalField($field, $dependentField, conditional);
                });
            });
        },
        
        /**
         * Check conditional field visibility
         */
        checkConditionalField: function($field, $dependentField, conditional) {
            let show = false;
            const currentValue = $dependentField.val();
            
            if (conditional.value && currentValue === conditional.value) {
                show = true;
            } else if (conditional.not_value && currentValue !== conditional.not_value) {
                show = true;
            }
            
            if (show) {
                $field.slideDown();
            } else {
                $field.slideUp();
                // Clear field values when hidden
                $field.find('input, select, textarea').val('');
            }
        },
        
        /**
         * Initialize progress bar
         */
        initProgressBar: function() {
            this.updateProgress();
            
            // Highlight current section
            this.highlightSection(1);
        },
        
        /**
         * Update progress bar
         */
        updateProgress: function() {
            const totalFields = $('#spiral-overthinking-detailed-form').find('input:visible, select:visible, textarea:visible').length;
            const filledFields = $('#spiral-overthinking-detailed-form').find('input:visible, select:visible, textarea:visible').filter(function() {
                return $(this).val() !== '';
            }).length;
            
            const progress = (filledFields / totalFields) * 100;
            $('.spiral-progress-fill').css('width', progress + '%');
            
            // Update step indicators
            $('.spiral-step').each(function(index) {
                const $step = $(this);
                const sectionKey = $step.data('section');
                const $section = $('[data-section="' + sectionKey + '"]');
                const sectionFields = $section.find('input:visible, select:visible, textarea:visible').length;
                const sectionFilled = $section.find('input:visible, select:visible, textarea:visible').filter(function() {
                    return $(this).val() !== '';
                }).length;
                
                if (sectionFilled === sectionFields && sectionFields > 0) {
                    $step.addClass('spiral-step-complete');
                } else if (sectionFilled > 0) {
                    $step.addClass('spiral-step-partial');
                } else {
                    $step.removeClass('spiral-step-complete spiral-step-partial');
                }
            });
        },
        
        /**
         * Navigate to section
         */
        navigateToSection: function(sectionKey) {
            const $section = $('[data-section="' + sectionKey + '"]');
            if ($section.length) {
                $('html, body').animate({
                    scrollTop: $section.offset().top - 100
                }, 500);
                
                // Update highlight
                const stepNumber = $('.spiral-step[data-section="' + sectionKey + '"]').data('step');
                this.highlightSection(stepNumber);
            }
        },
        
        /**
         * Highlight current section
         */
        highlightSection: function(stepNumber) {
            $('.spiral-step').removeClass('spiral-step-active');
            $('.spiral-step[data-step="' + stepNumber + '"]').addClass('spiral-step-active');
            this.state.currentSection = stepNumber;
        },
        
        /**
         * Start autosave
         */
        startAutosave: function() {
            const self = this;
            
            // Clear existing timer
            if (this.state.autosaveTimer) {
                clearInterval(this.state.autosaveTimer);
            }
            
            // Set new timer
            this.state.autosaveTimer = setInterval(function() {
                if (self.state.isDirty) {
                    self.saveDraft(true); // Silent save
                }
            }, this.config.autosaveInterval);
        },
        
        /**
         * Save draft
         */
        saveDraft: function(silent = false) {
            const self = this;
            const formData = $('#spiral-overthinking-detailed-form').serialize();
            
            if (!silent) {
                $('#spiral-save-draft').prop('disabled', true);
            }
            
            $.ajax({
                url: this.config.endpoints.saveDraft,
                type: 'POST',
                data: {
                    form_data: formData,
                    nonce: $('#overthinking_detailed_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        self.state.isDirty = false;
                        
                        if (!silent) {
                            self.showAutosaveIndicator();
                        }
                    }
                },
                complete: function() {
                    if (!silent) {
                        $('#spiral-save-draft').prop('disabled', false);
                    }
                }
            });
        },
        
        /**
         * Load draft
         */
        loadDraft: function(mode) {
            const draftData = localStorage.getItem('spiral_overthinking_draft_' + mode);
            if (draftData) {
                try {
                    const draft = JSON.parse(draftData);
                    // Populate form fields
                    $.each(draft, function(key, value) {
                        const $field = $('[name="' + key + '"]');
                        if ($field.length) {
                            $field.val(value).trigger('change');
                        }
                    });
                } catch (e) {
                    console.error('Failed to load draft:', e);
                }
            }
        },
        
        /**
         * Show autosave indicator
         */
        showAutosaveIndicator: function() {
            const $indicator = $('.spiral-autosave-indicator');
            $indicator.fadeIn(300).delay(2000).fadeOut(300);
        },
        
        /**
         * Schedule pattern check
         */
        schedulePatternCheck: function() {
            const self = this;
            
            // Clear existing timer
            if (this.state.patternCheckTimer) {
                clearTimeout(this.state.patternCheckTimer);
            }
            
            // Set new timer
            this.state.patternCheckTimer = setTimeout(function() {
                self.checkPatterns();
            }, this.config.patternCheckDelay);
        },
        
        /**
         * Check for patterns
         */
        checkPatterns: function() {
            const self = this;
            const primaryThought = $('#overthinking_primary_thought').val();
            
            if (primaryThought.length < 10) {
                return; // Too short to analyze
            }
            
            $.ajax({
                url: this.config.endpoints.checkPatterns,
                type: 'POST',
                data: {
                    thought: primaryThought,
                    nonce: $('#overthinking_quick_nonce, #overthinking_detailed_nonce').val()
                },
                success: function(response) {
                    if (response.success && response.data.patterns) {
                        self.displayPatternAlerts(response.data.patterns);
                    }
                }
            });
        },
        
        /**
         * Display pattern alerts
         */
        displayPatternAlerts: function(patterns) {
            const $container = $('.spiral-pattern-alerts');
            
            if ($container.length === 0) {
                // Create container if doesn't exist
                const $newContainer = $('<div class="spiral-pattern-alerts"></div>');
                $('.spiral-quick-header, .spiral-widget-header').after($newContainer);
            }
            
            // Clear existing alerts
            $('.spiral-pattern-alerts').empty();
            
            // Add new alerts
            $.each(patterns, function(index, pattern) {
                const $alert = $('<div class="spiral-alert spiral-alert-' + pattern.type + '">' +
                    '<span class="dashicons dashicons-' + pattern.icon + '"></span>' +
                    '<span class="spiral-alert-text">' + pattern.message + '</span>' +
                    '</div>');
                $('.spiral-pattern-alerts').append($alert);
            });
            
            // Animate in
            $('.spiral-pattern-alerts').hide().slideDown();
        },
        
        /**
         * Submit quick log
         */
        submitQuickLog: function($form) {
            const self = this;
            
            // Validate
            if (!this.validateForm($form)) {
                return;
            }
            
            // Show loading
            $('#spiral-quick-submit').prop('disabled', true);
            $('#spiral-quick-submit .spiral-button-text').hide();
            $('#spiral-quick-submit .spiral-button-loading').show();
            
            // Collect data
            const formData = $form.serialize();
            
            $.ajax({
                url: this.config.endpoints.quickLog,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        self.handleQuickLogSuccess(response.data);
                    } else {
                        self.showError(response.data.message || 'An error occurred');
                    }
                },
                error: function() {
                    self.showError('Connection error. Please try again.');
                },
                complete: function() {
                    $('#spiral-quick-submit').prop('disabled', false);
                    $('#spiral-quick-submit .spiral-button-text').show();
                    $('#spiral-quick-submit .spiral-button-loading').hide();
                }
            });
        },
        
        /**
         * Submit detailed log
         */
        submitDetailedLog: function($form) {
            const self = this;
            
            // Validate
            if (!this.validateForm($form)) {
                return;
            }
            
            // Show loading
            $('#spiral-submit-episode').prop('disabled', true);
            $('#spiral-submit-episode .spiral-button-text').hide();
            $('#spiral-submit-episode .spiral-button-loading').show();
            
            // Collect data
            const formData = $form.serialize();
            
            $.ajax({
                url: this.config.endpoints.detailedLog,
                type: 'POST',
                data: {
                    form_data: formData,
                    nonce: $('#overthinking_detailed_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        self.handleDetailedLogSuccess(response.data);
                    } else {
                        self.showError(response.data.message || 'An error occurred');
                    }
                },
                error: function() {
                    self.showError('Connection error. Please try again.');
                },
                complete: function() {
                    $('#spiral-submit-episode').prop('disabled', false);
                    $('#spiral-submit-episode .spiral-button-text').show();
                    $('#spiral-submit-episode .spiral-button-loading').hide();
                }
            });
        },
        
        /**
         * Validate form
         */
        validateForm: function($form) {
            let isValid = true;
            const self = this;
            
            // Check required fields
            $form.find('[required]:visible').each(function() {
                const $field = $(this);
                const value = $field.val();
                
                if (!value || value.length === 0) {
                    isValid = false;
                    self.highlightError($field);
                }
            });
            
            if (!isValid) {
                this.showError('Please fill in all required fields');
            }
            
            return isValid;
        },
        
        /**
         * Highlight field error
         */
        highlightError: function($field) {
            $field.addClass('spiral-field-error');
            
            // Remove error class after focus
            $field.one('focus', function() {
                $(this).removeClass('spiral-field-error');
            });
        },
        
        /**
         * Handle quick log success
         */
        handleQuickLogSuccess: function(data) {
            const self = this;
            
            // Clear form
            $('#spiral-overthinking-quick-form')[0].reset();
            $('.spiral-slider').trigger('input'); // Reset slider displays
            
            // Show success message
            $('#spiral-quick-success .spiral-success-text').text(data.message);
            $('#spiral-quick-success').slideDown();
            
            // Update recent episodes list
            if (data.recent_episodes) {
                this.updateRecentEpisodes(data.recent_episodes);
            }
            
            // Show patterns if any
            if (data.patterns && data.patterns.length > 0) {
                this.displayPatternAlerts(data.patterns);
            }
            
            // Show correlations if any
            if (data.correlations && data.correlations.length > 0) {
                this.updateCorrelationSummary(data.correlations);
            }
            
            // Hide success message after delay
            setTimeout(function() {
                $('#spiral-quick-success').slideUp();
            }, 5000);
            
            // Clear draft
            localStorage.removeItem('spiral_overthinking_draft_quick');
            this.state.isDirty = false;
        },
        
        /**
         * Handle detailed log success
         */
        handleDetailedLogSuccess: function(data) {
            const self = this;
            
            // Clear dirty flag
            this.state.isDirty = false;
            
            // Clear draft
            localStorage.removeItem('spiral_overthinking_draft_detailed');
            
            // Prepare insights content
            let insightsHtml = '<p>' + data.message + '</p>';
            
            if (data.patterns && data.patterns.length > 0) {
                insightsHtml += '<div class="spiral-insights-patterns">';
                insightsHtml += '<h4>Patterns Detected:</h4>';
                insightsHtml += '<ul>';
                $.each(data.patterns, function(index, pattern) {
                    insightsHtml += '<li>' + pattern.message + '</li>';
                });
                insightsHtml += '</ul>';
                insightsHtml += '</div>';
            }
            
            if (data.ai_analysis) {
                insightsHtml += '<div class="spiral-insights-ai">';
                insightsHtml += '<h4>AI Insights:</h4>';
                insightsHtml += '<p>' + data.ai_analysis + '</p>';
                insightsHtml += '</div>';
            }
            
            if (data.correlations && data.correlations.length > 0) {
                insightsHtml += '<div class="spiral-insights-correlations">';
                insightsHtml += '<h4>Episode Correlations:</h4>';
                insightsHtml += '<ul>';
                $.each(data.correlations, function(index, correlation) {
                    insightsHtml += '<li>' + correlation.description + '</li>';
                });
                insightsHtml += '</ul>';
                insightsHtml += '</div>';
            }
            
            // Show modal with insights
            $('#spiral-episode-insights').html(insightsHtml);
            $('#spiral-success-modal').fadeIn();
        },
        
        /**
         * Update recent episodes list
         */
        updateRecentEpisodes: function(episodes) {
            // This would update the recent episodes display
            // Implementation depends on the HTML structure
        },
        
        /**
         * Update correlation summary
         */
        updateCorrelationSummary: function(correlations) {
            // This would update the correlation summary display
            // Implementation depends on the HTML structure
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('#spiral-success-modal').fadeOut();
            
            // Reset form for new entry
            $('#spiral-overthinking-detailed-form')[0].reset();
            $('.spiral-slider').trigger('input');
            this.updateProgress();
            
            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 500);
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            const $error = $('#spiral-quick-error, #spiral-error-container').filter(':visible');
            $error.find('.spiral-error-text').text(message);
            $error.slideDown();
            
            // Auto hide after delay
            setTimeout(function() {
                $error.slideUp();
            }, 5000);
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        spiralEngineOverthinking.init();
    });
    
})(jQuery);

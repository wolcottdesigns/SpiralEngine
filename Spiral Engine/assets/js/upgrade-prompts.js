/**
 * SPIRAL Engine Upgrade Prompts JavaScript
 * 
 * @package    SPIRAL_Engine
 * @subpackage Assets
 * @file       assets/js/upgrade-prompts.js
 * @since      1.0.0
 */

(function($) {
    'use strict';

    /**
     * SPIRAL Engine Upgrade Prompts Handler
     */
    window.SpiralEngineUpgradePrompts = {
        
        // Configuration
        config: {
            animationSpeed: 300,
            dismissDuration: 7, // days
            cookiePrefix: 'spiralengine_dismissed_',
            trackingEnabled: true
        },

        // Active prompts
        activePrompts: {},

        /**
         * Initialize upgrade prompts
         */
        init: function() {
            this.bindEvents();
            this.initializePrompts();
            this.checkDelayedPrompts();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Dismiss button
            $(document).on('click', '.sp-prompt-dismiss', function(e) {
                e.preventDefault();
                self.dismissPrompt($(this).closest('.spiralengine-upgrade-prompt'));
            });

            // Track clicks on upgrade buttons
            $(document).on('click', '.sp-track-click', function() {
                var feature = $(this).data('feature');
                if (feature && self.config.trackingEnabled) {
                    self.trackClick(feature);
                }
            });

            // Unlock buttons in blur overlays
            $(document).on('click', '.sp-unlock-button', function(e) {
                e.preventDefault();
                var tier = $(this).data('tier');
                self.showUpgradeModal(tier);
            });

            // Inline CTA animations
            $(document).on('mouseenter', '.sp-inline-cta', function() {
                $(this).addClass('sp-hover');
            }).on('mouseleave', '.sp-inline-cta', function() {
                $(this).removeClass('sp-hover');
            });

            // Expand prompt interactions
            $(document).on('click', '.sp-expand-button', function(e) {
                e.preventDefault();
                var tier = $(this).data('tier');
                self.showExpandedContent($(this), tier);
            });
        },

        /**
         * Initialize all prompts on page
         */
        initializePrompts: function() {
            var self = this;

            $('.spiralengine-upgrade-prompt').each(function() {
                var $prompt = $(this);
                var promptId = $prompt.attr('id');
                var feature = $prompt.data('feature');

                // Check if dismissed
                if (!self.isDismissed(feature)) {
                    self.activePrompts[promptId] = {
                        element: $prompt,
                        feature: feature,
                        shown: Date.now()
                    };

                    // Animate in
                    setTimeout(function() {
                        $prompt.addClass('sp-prompt-active');
                    }, 100);
                } else {
                    $prompt.remove();
                }
            });
        },

        /**
         * Check for delayed prompts
         */
        checkDelayedPrompts: function() {
            var self = this;

            // Check scroll-triggered prompts
            $(window).on('scroll.spiralengine', function() {
                var scrollPercent = ($(window).scrollTop() / ($(document).height() - $(window).height())) * 100;

                if (scrollPercent > 50) {
                    self.showDelayedPrompt('scroll_50');
                }

                if (scrollPercent > 80) {
                    self.showDelayedPrompt('scroll_80');
                    $(window).off('scroll.spiralengine');
                }
            });

            // Time-based prompts
            setTimeout(function() {
                self.showDelayedPrompt('time_30');
            }, 30000);

            // Exit intent
            $(document).on('mouseleave', function(e) {
                if (e.clientY <= 0) {
                    self.showDelayedPrompt('exit_intent');
                }
            });
        },

        /**
         * Show delayed prompt
         */
        showDelayedPrompt: function(trigger) {
            var $prompt = $('.spiralengine-delayed-prompt[data-trigger="' + trigger + '"]');
            
            if ($prompt.length && !$prompt.hasClass('sp-shown')) {
                var feature = $prompt.data('feature');
                
                if (!this.isDismissed(feature)) {
                    $prompt.addClass('sp-shown sp-prompt-active');
                    
                    // Track impression
                    this.trackImpression(feature, trigger);
                }
            }
        },

        /**
         * Dismiss prompt
         */
        dismissPrompt: function($prompt) {
            var self = this;
            var feature = $prompt.data('feature');

            // Animate out
            $prompt.removeClass('sp-prompt-active');
            
            setTimeout(function() {
                $prompt.fadeOut(self.config.animationSpeed, function() {
                    $prompt.remove();
                    delete self.activePrompts[$prompt.attr('id')];
                });
            }, 300);

            // Save dismissal
            this.saveDismissal(feature);

            // Track dismissal
            this.trackDismissal(feature);
        },

        /**
         * Save dismissal
         */
        saveDismissal: function(feature) {
            $.ajax({
                url: spiralengineUpgrade.ajax_url,
                type: 'POST',
                data: {
                    action: 'spiralengine_dismiss_upgrade_prompt',
                    feature: feature,
                    nonce: spiralengineUpgrade.nonce
                }
            });

            // Also save in localStorage for immediate effect
            var dismissals = this.getDismissals();
            dismissals[feature] = Date.now();
            localStorage.setItem('spiralengine_dismissals', JSON.stringify(dismissals));
        },

        /**
         * Get dismissals from localStorage
         */
        getDismissals: function() {
            var dismissals = localStorage.getItem('spiralengine_dismissals');
            return dismissals ? JSON.parse(dismissals) : {};
        },

        /**
         * Check if prompt is dismissed
         */
        isDismissed: function(feature) {
            var dismissals = this.getDismissals();
            
            if (!dismissals[feature]) {
                return false;
            }

            // Check if dismissal has expired
            var dismissTime = dismissals[feature];
            var expiryTime = dismissTime + (this.config.dismissDuration * 24 * 60 * 60 * 1000);
            
            if (Date.now() > expiryTime) {
                delete dismissals[feature];
                localStorage.setItem('spiralengine_dismissals', JSON.stringify(dismissals));
                return false;
            }

            return true;
        },

        /**
         * Track click
         */
        trackClick: function(feature) {
            $.ajax({
                url: spiralengineUpgrade.ajax_url,
                type: 'POST',
                data: {
                    action: 'spiralengine_track_upgrade_click',
                    feature: feature,
                    nonce: spiralengineUpgrade.nonce
                }
            });

            // Local analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'upgrade_click', {
                    'event_category': 'engagement',
                    'event_label': feature
                });
            }
        },

        /**
         * Track impression
         */
        trackImpression: function(feature, trigger) {
            // Implementation would send analytics data
            if (typeof gtag !== 'undefined') {
                gtag('event', 'upgrade_impression', {
                    'event_category': 'engagement',
                    'event_label': feature,
                    'trigger': trigger || 'immediate'
                });
            }
        },

        /**
         * Track dismissal
         */
        trackDismissal: function(feature) {
            // Implementation would send analytics data
            if (typeof gtag !== 'undefined') {
                gtag('event', 'upgrade_dismiss', {
                    'event_category': 'engagement',
                    'event_label': feature
                });
            }
        },

        /**
         * Show upgrade modal
         */
        showUpgradeModal: function(tier) {
            // This would integrate with the main restrictions.js modal system
            if (window.SpiralEngineRestrictions) {
                window.SpiralEngineRestrictions.showUpgradeModal(tier, 'prompt_unlock');
            }
        },

        /**
         * Show expanded content
         */
        showExpandedContent: function($button, tier) {
            var $container = $button.closest('.sp-expand-prompt').prev();
            var self = this;

            // Create preview modal
            var modalHtml = '<div class="spiralengine-preview-modal">' +
                '<div class="sp-preview-overlay"></div>' +
                '<div class="sp-preview-content">' +
                    '<button class="sp-preview-close"><span class="dashicons dashicons-no"></span></button>' +
                    '<div class="sp-preview-header">' +
                        '<h3>Content Preview</h3>' +
                        '<p>Unlock full access with ' + this.getTierName(tier) + ' membership</p>' +
                    '</div>' +
                    '<div class="sp-preview-body">' +
                        '<div class="sp-preview-text">' +
                            this.generatePreviewContent($container) +
                        '</div>' +
                        '<div class="sp-preview-fade"></div>' +
                    '</div>' +
                    '<div class="sp-preview-footer">' +
                        '<a href="#" class="sp-preview-upgrade sp-track-click" data-feature="content_preview">' +
                            'Unlock Full Content' +
                        '</a>' +
                    '</div>' +
                '</div>' +
            '</div>';

            $('body').append(modalHtml);

            // Show modal
            setTimeout(function() {
                $('.spiralengine-preview-modal').addClass('sp-modal-active');
            }, 10);

            // Close handler
            $(document).on('click', '.sp-preview-close, .sp-preview-overlay', function() {
                self.closePreviewModal();
            });
        },

        /**
         * Generate preview content
         */
        generatePreviewContent: function($container) {
            // Clone content and expand
            var $clone = $container.clone();
            $clone.css({
                'max-height': 'none',
                'overflow': 'visible'
            });
            $clone.find('.sp-partial-fade').remove();

            // Add blur effect to bottom portion
            var fullText = $clone.text();
            var previewLength = Math.floor(fullText.length * 0.6);
            var previewText = fullText.substring(0, previewLength);
            
            return '<p>' + previewText + '...</p>' +
                   '<p class="sp-preview-blurred">' + fullText.substring(previewLength) + '</p>';
        },

        /**
         * Close preview modal
         */
        closePreviewModal: function() {
            var $modal = $('.spiralengine-preview-modal');
            
            $modal.removeClass('sp-modal-active');
            
            setTimeout(function() {
                $modal.remove();
            }, 300);
        },

        /**
         * Get tier name
         */
        getTierName: function(tier) {
            var tierNames = {
                'explorer': 'Explorer',
                'pioneer': 'Pioneer',
                'navigator': 'Navigator',
                'voyager': 'Voyager'
            };
            
            return tierNames[tier] || 'Premium';
        },

        /**
         * A/B Test tracking
         */
        trackVariantPerformance: function() {
            // Collect all visible prompts and their variants
            var variantData = {};
            
            $('.spiralengine-upgrade-prompt:visible').each(function() {
                var $prompt = $(this);
                var feature = $prompt.data('feature');
                var variant = $prompt.data('variant') || 'a';
                
                if (!variantData[feature]) {
                    variantData[feature] = {};
                }
                
                variantData[feature][variant] = {
                    impressions: 1,
                    position: $prompt.offset().top,
                    size: {
                        width: $prompt.width(),
                        height: $prompt.height()
                    }
                };
            });

            // Send to analytics
            if (Object.keys(variantData).length > 0) {
                $.ajax({
                    url: spiralengineUpgrade.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_track_variant_performance',
                        variants: variantData,
                        nonce: spiralengineUpgrade.nonce
                    }
                });
            }
        },

        /**
         * Optimize prompt placement
         */
        optimizePlacement: function() {
            var self = this;
            
            // Check viewport and adjust positions
            $('.spiralengine-upgrade-prompt').each(function() {
                var $prompt = $(this);
                var promptTop = $prompt.offset().top;
                var windowHeight = $(window).height();
                var scrollTop = $(window).scrollTop();
                
                // If prompt is in viewport
                if (promptTop >= scrollTop && promptTop <= (scrollTop + windowHeight)) {
                    // Ensure it's not covering important content
                    self.adjustPromptPosition($prompt);
                }
            });
        },

        /**
         * Adjust prompt position to avoid content overlap
         */
        adjustPromptPosition: function($prompt) {
            // Check for overlapping elements
            var promptRect = $prompt[0].getBoundingClientRect();
            var importantElements = $('.spiralengine-widget, .sp-important-content');
            
            importantElements.each(function() {
                var elemRect = this.getBoundingClientRect();
                
                // Check for overlap
                if (!(promptRect.right < elemRect.left || 
                      promptRect.left > elemRect.right || 
                      promptRect.bottom < elemRect.top || 
                      promptRect.top > elemRect.bottom)) {
                    
                    // Adjust position
                    var newTop = elemRect.bottom + 20;
                    $prompt.css('top', newTop + 'px');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SpiralEngineUpgradePrompts.init();
        
        // Track variant performance after 5 seconds
        setTimeout(function() {
            SpiralEngineUpgradePrompts.trackVariantPerformance();
        }, 5000);
        
        // Optimize placement on scroll and resize
        var optimizeTimeout;
        $(window).on('scroll resize', function() {
            clearTimeout(optimizeTimeout);
            optimizeTimeout = setTimeout(function() {
                SpiralEngineUpgradePrompts.optimizePlacement();
            }, 100);
        });
    });

})(jQuery);

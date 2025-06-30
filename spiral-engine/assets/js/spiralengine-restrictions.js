/**
 * SPIRAL Engine Client-Side Restrictions
 * 
 * @package    SPIRAL_Engine
 * @subpackage Assets
 * @file       assets/js/spiralengine-restrictions.js
 * @since      1.0.0
 */

(function($) {
    'use strict';

    /**
     * SPIRAL Engine Restrictions Manager
     */
    window.SpiralEngineRestrictions = {
        
        // Configuration
        config: {
            blurIntensity: 8,
            overlayOpacity: 0.95,
            animationDuration: 300,
            modalZIndex: 999999,
            previewDelay: 2000,
            clickTracking: true
        },

        // Current user data
        userData: {
            tier: 'discovery',
            tierLevel: 0,
            features: []
        },

        // Cached access checks
        accessCache: {},

        // Tracking data
        tracking: {
            impressions: {},
            clicks: {},
            hovers: {}
        },

        /**
         * Initialize restrictions system
         */
        init: function() {
            this.loadUserData();
            this.bindEvents();
            this.applyRestrictions();
            this.initializeModals();
            this.startObservers();
        },

        /**
         * Load user data
         */
        loadUserData: function() {
            if (typeof spiralengineUser !== 'undefined') {
                this.userData = $.extend(this.userData, spiralengineUser);
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Click on restricted content
            $(document).on('click', '.spiralengine-restricted', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.handleRestrictedClick($(this));
            });

            // Hover effects
            $(document).on('mouseenter', '.spiralengine-blur', function() {
                self.handleRestrictedHover($(this), 'enter');
            });

            $(document).on('mouseleave', '.spiralengine-blur', function() {
                self.handleRestrictedHover($(this), 'leave');
            });

            // Upgrade button clicks
            $(document).on('click', '.sp-upgrade-button, .sp-inline-cta', function(e) {
                if (self.config.clickTracking) {
                    self.trackUpgradeClick($(this));
                }
            });

            // Modal interactions
            $(document).on('click', '.sp-modal-close, .sp-modal-overlay', function() {
                self.closeUpgradeModal();
            });

            // Preview triggers
            $(document).on('click', '.sp-preview-trigger', function(e) {
                e.preventDefault();
                self.showFeaturePreview($(this).data('feature'));
            });

            // Dismiss prompts
            $(document).on('click', '.sp-prompt-dismiss', function() {
                self.dismissPrompt($(this).closest('.spiralengine-upgrade-prompt'));
            });
        },

        /**
         * Apply restrictions to elements
         */
        applyRestrictions: function() {
            var self = this;

            // Find all restricted elements
            $('[data-requires-tier]').each(function() {
                var $element = $(this);
                var requiredTier = $element.data('requires-tier');
                var restrictionType = $element.data('restriction-type') || 'blur';

                if (!self.hasAccess(requiredTier)) {
                    self.applyRestriction($element, restrictionType, requiredTier);
                }
            });

            // Widget section restrictions
            $('.sp-widget-section[data-gated="true"]').each(function() {
                var $section = $(this);
                self.applyWidgetRestriction($section);
            });
        },

        /**
         * Apply restriction to element
         */
        applyRestriction: function($element, type, requiredTier) {
            $element.addClass('spiralengine-restricted spiralengine-' + type);
            $element.attr('data-required-tier', requiredTier);

            switch (type) {
                case 'blur':
                    this.applyBlurEffect($element);
                    break;
                case 'overlay':
                    this.applyOverlayEffect($element);
                    break;
                case 'partial':
                    this.applyPartialEffect($element);
                    break;
                case 'disable':
                    this.applyDisableEffect($element);
                    break;
            }

            // Track impression
            this.trackImpression($element, requiredTier);
        },

        /**
         * Apply blur effect
         */
        applyBlurEffect: function($element) {
            var self = this;

            // Add blur wrapper if needed
            if (!$element.parent().hasClass('sp-blur-wrapper')) {
                $element.wrap('<div class="sp-blur-wrapper"></div>');
            }

            var $wrapper = $element.parent();

            // Apply CSS blur
            $element.css({
                'filter': 'blur(' + this.config.blurIntensity + 'px)',
                'user-select': 'none',
                'pointer-events': 'none'
            });

            // Add overlay message
            if (!$wrapper.find('.sp-blur-overlay').length) {
                var tierInfo = this.getTierInfo($element.data('required-tier'));
                var overlayHtml = this.buildBlurOverlay(tierInfo);
                $wrapper.append(overlayHtml);
            }
        },

        /**
         * Apply overlay effect
         */
        applyOverlayEffect: function($element) {
            var self = this;

            $element.css('position', 'relative');

            if (!$element.find('.sp-restriction-overlay').length) {
                var tierInfo = this.getTierInfo($element.data('required-tier'));
                var overlayHtml = this.buildFullOverlay(tierInfo);
                $element.append(overlayHtml);
            }
        },

        /**
         * Apply partial effect (show teaser)
         */
        applyPartialEffect: function($element) {
            var maxHeight = $element.data('preview-height') || 200;
            
            $element.css({
                'max-height': maxHeight + 'px',
                'overflow': 'hidden',
                'position': 'relative'
            });

            // Add fade gradient
            if (!$element.find('.sp-partial-fade').length) {
                $element.append('<div class="sp-partial-fade"></div>');
            }

            // Add expand button
            if (!$element.next('.sp-expand-prompt').length) {
                var tierInfo = this.getTierInfo($element.data('required-tier'));
                var expandHtml = this.buildExpandPrompt(tierInfo);
                $element.after(expandHtml);
            }
        },

        /**
         * Apply disable effect
         */
        applyDisableEffect: function($element) {
            $element.find('input, button, select, textarea').prop('disabled', true);
            $element.addClass('sp-disabled');

            // Add disabled message
            if (!$element.find('.sp-disabled-message').length) {
                var tierInfo = this.getTierInfo($element.data('required-tier'));
                var message = '<div class="sp-disabled-message">' +
                    '<span class="dashicons dashicons-lock"></span> ' +
                    'Requires ' + tierInfo.name + ' membership' +
                    '</div>';
                $element.prepend(message);
            }
        },

        /**
         * Build blur overlay HTML
         */
        buildBlurOverlay: function(tierInfo) {
            return '<div class="sp-blur-overlay">' +
                '<div class="sp-blur-content">' +
                    '<span class="sp-tier-icon dashicons ' + tierInfo.icon + '"></span>' +
                    '<h4>Unlock with ' + tierInfo.name + '</h4>' +
                    '<p>' + tierInfo.description + '</p>' +
                    '<button class="sp-unlock-button" data-tier="' + tierInfo.key + '">' +
                        'View Plans <span class="dashicons dashicons-arrow-right-alt"></span>' +
                    '</button>' +
                '</div>' +
            '</div>';
        },

        /**
         * Build full overlay HTML
         */
        buildFullOverlay: function(tierInfo) {
            return '<div class="sp-restriction-overlay" style="background: ' + this.getTierGradient(tierInfo.key) + '">' +
                '<div class="sp-overlay-content">' +
                    '<div class="sp-overlay-inner">' +
                        '<span class="sp-tier-badge">' + tierInfo.name + '</span>' +
                        '<h3>Premium Feature</h3>' +
                        '<p>' + tierInfo.description + '</p>' +
                        '<div class="sp-feature-list">' +
                            this.buildFeatureList(tierInfo.key) +
                        '</div>' +
                        '<button class="sp-upgrade-cta" data-tier="' + tierInfo.key + '">' +
                            'Upgrade to ' + tierInfo.name +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        },

        /**
         * Build expand prompt HTML
         */
        buildExpandPrompt: function(tierInfo) {
            return '<div class="sp-expand-prompt">' +
                '<p>Continue reading with ' + tierInfo.name + ' membership</p>' +
                '<button class="sp-expand-button" data-tier="' + tierInfo.key + '">' +
                    'Unlock Full Content' +
                '</button>' +
            '</div>';
        },

        /**
         * Handle restricted content click
         */
        handleRestrictedClick: function($element) {
            var requiredTier = $element.data('required-tier');
            var feature = $element.data('feature') || 'unknown';

            // Track click
            this.trackClick(feature, requiredTier);

            // Show upgrade modal
            this.showUpgradeModal(requiredTier, feature);
        },

        /**
         * Handle restricted hover
         */
        handleRestrictedHover: function($element, action) {
            var $overlay = $element.siblings('.sp-blur-overlay');

            if (action === 'enter') {
                $overlay.addClass('sp-hover');
                
                // Reduce blur slightly on hover
                $element.css('filter', 'blur(' + (this.config.blurIntensity - 2) + 'px)');

                // Track hover
                var feature = $element.data('feature') || 'unknown';
                this.trackHover(feature);
            } else {
                $overlay.removeClass('sp-hover');
                
                // Restore full blur
                $element.css('filter', 'blur(' + this.config.blurIntensity + 'px)');
            }
        },

        /**
         * Show upgrade modal
         */
        showUpgradeModal: function(requiredTier, feature) {
            var self = this;
            var tierInfo = this.getTierInfo(requiredTier);

            // Build modal HTML
            var modalHtml = '<div class="spiralengine-upgrade-modal" id="sp-upgrade-modal">' +
                '<div class="sp-modal-overlay"></div>' +
                '<div class="sp-modal-content">' +
                    '<button class="sp-modal-close"><span class="dashicons dashicons-no"></span></button>' +
                    '<div class="sp-modal-header" style="background: ' + this.getTierGradient(requiredTier) + '">' +
                        '<span class="sp-modal-icon dashicons ' + tierInfo.icon + '"></span>' +
                        '<h2>Upgrade to ' + tierInfo.name + '</h2>' +
                        '<p class="sp-modal-tagline">' + tierInfo.description + '</p>' +
                    '</div>' +
                    '<div class="sp-modal-body">' +
                        '<div class="sp-feature-grid">' +
                            this.buildModalFeatures(requiredTier) +
                        '</div>' +
                        '<div class="sp-modal-comparison">' +
                            this.buildTierComparison(requiredTier) +
                        '</div>' +
                    '</div>' +
                    '<div class="sp-modal-footer">' +
                        '<div class="sp-pricing-info">' +
                            this.getPricingDisplay(requiredTier) +
                        '</div>' +
                        '<a href="' + this.getUpgradeUrl(requiredTier) + '" ' +
                           'class="sp-modal-upgrade-button sp-track-click" ' +
                           'data-feature="' + feature + '" ' +
                           'data-context="modal">' +
                            'Upgrade Now <span class="dashicons dashicons-arrow-right-alt"></span>' +
                        '</a>' +
                    '</div>' +
                '</div>' +
            '</div>';

            // Remove any existing modal
            $('#sp-upgrade-modal').remove();

            // Add modal to body
            $('body').append(modalHtml);

            // Animate in
            setTimeout(function() {
                $('#sp-upgrade-modal').addClass('sp-modal-active');
            }, 10);

            // Track modal view
            this.trackModalView(feature, requiredTier);
        },

        /**
         * Close upgrade modal
         */
        closeUpgradeModal: function() {
            var $modal = $('#sp-upgrade-modal');
            
            $modal.removeClass('sp-modal-active');
            
            setTimeout(function() {
                $modal.remove();
            }, this.config.animationDuration);
        },

        /**
         * Show feature preview
         */
        showFeaturePreview: function(feature) {
            var self = this;

            // Check cache for preview availability
            this.checkFeatureAccess(feature, function(access) {
                if (access.has_preview) {
                    self.loadFeaturePreview(feature, access);
                } else {
                    self.showUpgradeModal(access.required_tier, feature);
                }
            });
        },

        /**
         * Load feature preview
         */
        loadFeaturePreview: function(feature, access) {
            var previewHtml = '<div class="spiralengine-feature-preview">' +
                '<div class="sp-preview-header">' +
                    '<h3>Preview: ' + access.feature_name + '</h3>' +
                    '<span class="sp-preview-badge">Limited Preview</span>' +
                '</div>' +
                '<div class="sp-preview-content" id="sp-preview-content">' +
                    '<div class="sp-preview-loading">' +
                        '<span class="dashicons dashicons-update sp-spin"></span>' +
                        '<p>Loading preview...</p>' +
                    '</div>' +
                '</div>' +
                '<div class="sp-preview-footer">' +
                    '<p>Like what you see? Unlock the full feature:</p>' +
                    '<a href="' + access.upgrade_url + '" class="sp-preview-upgrade">' +
                        'Upgrade to ' + access.required_tier_name +
                    '</a>' +
                '</div>' +
            '</div>';

            // Show preview in modal
            this.showPreviewModal(previewHtml);

            // Load preview content via AJAX
            this.loadPreviewContent(feature);
        },

        /**
         * Dismiss upgrade prompt
         */
        dismissPrompt: function($prompt) {
            var feature = $prompt.data('feature');

            // Animate out
            $prompt.fadeOut(this.config.animationDuration, function() {
                $prompt.remove();
            });

            // Save dismissal
            if (feature) {
                this.saveDismissal(feature);
            }
        },

        /**
         * Widget-specific restrictions
         */
        applyWidgetRestriction: function($section) {
            var restrictionType = $section.data('restriction') || 'blur';
            var requiredTier = $section.data('required-tier');

            // Add wrapper for styling
            $section.addClass('sp-widget-restricted');

            // Apply restriction based on type
            switch (restrictionType) {
                case 'blur':
                    this.applyWidgetBlur($section, requiredTier);
                    break;
                case 'hide':
                    this.hideWidgetSection($section, requiredTier);
                    break;
                case 'preview':
                    this.showWidgetPreview($section, requiredTier);
                    break;
            }
        },

        /**
         * Apply widget blur
         */
        applyWidgetBlur: function($section, requiredTier) {
            // Blur content
            $section.find('.sp-section-content').css({
                'filter': 'blur(' + this.config.blurIntensity + 'px)',
                'pointer-events': 'none'
            });

            // Add unlock prompt
            var unlockHtml = this.buildWidgetUnlockPrompt(requiredTier);
            $section.prepend(unlockHtml);
        },

        /**
         * Check feature access via AJAX
         */
        checkFeatureAccess: function(feature, callback) {
            var self = this;

            // Check cache first
            if (this.accessCache[feature]) {
                callback(this.accessCache[feature]);
                return;
            }

            $.ajax({
                url: spiralengineAjax.url,
                type: 'POST',
                data: {
                    action: 'spiralengine_check_feature_access',
                    feature: feature,
                    nonce: spiralengineAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.accessCache[feature] = response.data;
                        callback(response.data);
                    }
                }
            });
        },

        /**
         * Track impression
         */
        trackImpression: function($element, tier) {
            var feature = $element.data('feature') || 'element_' + $element.attr('id');
            
            if (!this.tracking.impressions[feature]) {
                this.tracking.impressions[feature] = {
                    count: 0,
                    tier: tier,
                    first_seen: Date.now()
                };
            }
            
            this.tracking.impressions[feature].count++;
            this.tracking.impressions[feature].last_seen = Date.now();
        },

        /**
         * Track click
         */
        trackClick: function(feature, tier) {
            if (!this.tracking.clicks[feature]) {
                this.tracking.clicks[feature] = {
                    count: 0,
                    tier: tier
                };
            }
            
            this.tracking.clicks[feature].count++;
            this.tracking.clicks[feature].last_click = Date.now();

            // Send to server
            this.sendTrackingData('click', feature, tier);
        },

        /**
         * Track upgrade button click
         */
        trackUpgradeClick: function($button) {
            var feature = $button.data('feature');
            var context = $button.data('context') || 'inline';

            $.ajax({
                url: spiralengineAjax.url,
                type: 'POST',
                data: {
                    action: 'spiralengine_track_upgrade_click',
                    feature: feature,
                    context: context,
                    nonce: spiralengineAjax.nonce
                }
            });
        },

        /**
         * Send tracking data
         */
        sendTrackingData: function(eventType, feature, tier) {
            // Debounced tracking to avoid too many requests
            if (this.trackingTimeout) {
                clearTimeout(this.trackingTimeout);
            }

            var self = this;
            this.trackingTimeout = setTimeout(function() {
                $.ajax({
                    url: spiralengineAjax.url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_track_restriction_event',
                        event_type: eventType,
                        feature: feature,
                        tier: tier,
                        tracking_data: self.tracking,
                        nonce: spiralengineAjax.nonce
                    }
                });
            }, 1000);
        },

        /**
         * Get tier info
         */
        getTierInfo: function(tierKey) {
            var tiers = {
                'discovery': {
                    key: 'discovery',
                    name: 'Discovery',
                    icon: 'dashicons-search',
                    color: '#808080',
                    description: 'Your journey to understanding begins here'
                },
                'explorer': {
                    key: 'explorer',
                    name: 'Explorer',
                    icon: 'dashicons-explore', 
                    color: '#3B82F6',
                    description: 'Unlock pattern detection and correlation insights'
                },
                'pioneer': {
                    key: 'pioneer',
                    name: 'Pioneer',
                    icon: 'dashicons-flag',
                    color: '#8B5CF6',
                    description: 'Advanced analytics and personalized recommendations'
                },
                'navigator': {
                    key: 'navigator',
                    name: 'Navigator',
                    icon: 'dashicons-location',
                    color: '#F59E0B',
                    description: 'AI-powered forecasts and export capabilities'
                },
                'voyager': {
                    key: 'voyager',
                    name: 'Voyager',
                    icon: 'dashicons-star-filled',
                    color: '#EF4444',
                    description: 'Complete platform access with API integration'
                }
            };

            return tiers[tierKey] || tiers['discovery'];
        },

        /**
         * Get tier gradient
         */
        getTierGradient: function(tier) {
            var gradients = {
                'explorer': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'pioneer': 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                'navigator': 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                'voyager': 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)'
            };

            return gradients[tier] || gradients['explorer'];
        },

        /**
         * Get upgrade URL
         */
        getUpgradeUrl: function(tier) {
            if (typeof spiralengineUpgrade !== 'undefined' && spiralengineUpgrade.urls[tier]) {
                return spiralengineUpgrade.urls[tier];
            }
            return '/membership/upgrade/?tier=' + tier;
        },

        /**
         * Check if user has access to tier
         */
        hasAccess: function(requiredTier) {
            var tierLevels = {
                'discovery': 0,
                'explorer': 1,
                'pioneer': 2,
                'navigator': 3,
                'voyager': 4
            };

            var requiredLevel = tierLevels[requiredTier] || 0;
            return this.userData.tierLevel >= requiredLevel;
        },

        /**
         * Initialize mutation observer for dynamic content
         */
        startObservers: function() {
            var self = this;

            // Watch for new restricted content
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            var $node = $(node);
                            if ($node.is('[data-requires-tier]') || $node.find('[data-requires-tier]').length) {
                                self.applyRestrictions();
                            }
                        }
                    });
                });
            });

            // Start observing
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * Build feature list for tier
         */
        buildFeatureList: function(tier) {
            // This would be populated from server data
            var features = {
                'explorer': [
                    'Pattern Detection',
                    'Episode Correlations',
                    'Timeline View',
                    'Severity Trends'
                ],
                'pioneer': [
                    'Everything in Explorer',
                    'Advanced Analytics',
                    'Trigger Analysis',
                    'Personalized Insights'
                ],
                'navigator': [
                    'Everything in Pioneer',
                    'AI Forecasting',
                    'Data Export',
                    'Caregiver Portal'
                ],
                'voyager': [
                    'Everything in Navigator',
                    'API Access',
                    'Custom Widgets',
                    'Priority Support'
                ]
            };

            var list = '<ul class="sp-feature-checklist">';
            var tierFeatures = features[tier] || [];
            
            tierFeatures.forEach(function(feature) {
                list += '<li><span class="dashicons dashicons-yes"></span> ' + feature + '</li>';
            });
            
            list += '</ul>';
            return list;
        },

        /**
         * Save dismissal to prevent showing again
         */
        saveDismissal: function(feature) {
            $.ajax({
                url: spiralengineAjax.url,
                type: 'POST',
                data: {
                    action: 'spiralengine_dismiss_upgrade_prompt',
                    feature: feature,
                    nonce: spiralengineAjax.nonce
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SpiralEngineRestrictions.init();
    });

})(jQuery);

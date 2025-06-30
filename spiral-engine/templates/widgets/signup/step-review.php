<?php
// templates/widgets/signup/step-review.php

/**
 * Signup Widget - Step 3: Review & Complete
 * 
 * Final review and submission of signup form
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="spiralengine-step spiralengine-step-3" data-step="3">
    <h3 class="spiralengine-step-title"><?php _e('Review & Complete', 'spiralengine'); ?></h3>
    <p class="spiralengine-step-description"><?php _e('Please review your information before completing your registration', 'spiralengine'); ?></p>
    
    <div class="spiralengine-review-container">
        <!-- Account Information -->
        <div class="spiralengine-review-section">
            <h4 class="spiralengine-review-section-title">
                <?php _e('Account Information', 'spiralengine'); ?>
                <button type="button" class="spiralengine-edit-section" data-edit-step="1">
                    <?php _e('Edit', 'spiralengine'); ?>
                </button>
            </h4>
            
            <div class="spiralengine-review-content">
                <div class="spiralengine-review-item">
                    <span class="spiralengine-review-label"><?php _e('Username:', 'spiralengine'); ?></span>
                    <span class="spiralengine-review-value" id="review-username"></span>
                </div>
                
                <div class="spiralengine-review-item">
                    <span class="spiralengine-review-label"><?php _e('Email:', 'spiralengine'); ?></span>
                    <span class="spiralengine-review-value" id="review-email"></span>
                </div>
                
                <div class="spiralengine-review-item">
                    <span class="spiralengine-review-label"><?php _e('Name:', 'spiralengine'); ?></span>
                    <span class="spiralengine-review-value" id="review-name"></span>
                </div>
                
                <div class="spiralengine-review-item">
                    <span class="spiralengine-review-label"><?php _e('Gender:', 'spiralengine'); ?></span>
                    <span class="spiralengine-review-value" id="review-gender"></span>
                </div>
                
                <div class="spiralengine-review-item spiralengine-wellness-review" style="display: none;">
                    <span class="spiralengine-review-label"><?php _e('Wellness Tracking:', 'spiralengine'); ?></span>
                    <span class="spiralengine-review-value spiralengine-review-enabled">
                        <span class="spiralengine-checkmark">✓</span>
                        <?php _e('Enabled', 'spiralengine'); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- SPIRAL Assessment Results -->
        <div class="spiralengine-review-section">
            <h4 class="spiralengine-review-section-title">
                <?php _e('SPIRAL Assessment Results', 'spiralengine'); ?>
                <button type="button" class="spiralengine-edit-section" data-edit-step="2">
                    <?php _e('Retake', 'spiralengine'); ?>
                </button>
            </h4>
            
            <div class="spiralengine-review-content">
                <div class="spiralengine-assessment-results">
                    <div class="spiralengine-results-score">
                        <div class="spiralengine-score-circle">
                            <span class="spiralengine-score-value" id="review-score">0</span>
                            <span class="spiralengine-score-label"><?php _e('Total Score', 'spiralengine'); ?></span>
                        </div>
                    </div>
                    
                    <div class="spiralengine-results-intensity">
                        <span class="spiralengine-intensity-label"><?php _e('Intensity Level:', 'spiralengine'); ?></span>
                        <span class="spiralengine-intensity-value" id="review-intensity"></span>
                    </div>
                    
                    <div class="spiralengine-results-breakdown">
                        <h5><?php _e('Score Breakdown:', 'spiralengine'); ?></h5>
                        <div class="spiralengine-breakdown-items">
                            <div class="spiralengine-breakdown-item">
                                <span class="spiralengine-breakdown-label"><?php _e('Acceleration:', 'spiralengine'); ?></span>
                                <span class="spiralengine-breakdown-value" id="review-acceleration">0</span>
                            </div>
                            <div class="spiralengine-breakdown-item">
                                <span class="spiralengine-breakdown-label"><?php _e('Catastrophizing:', 'spiralengine'); ?></span>
                                <span class="spiralengine-breakdown-value" id="review-catastrophizing">0</span>
                            </div>
                            <div class="spiralengine-breakdown-item">
                                <span class="spiralengine-breakdown-label"><?php _e('Loss of Control:', 'spiralengine'); ?></span>
                                <span class="spiralengine-breakdown-value" id="review-loss-of-control">0</span>
                            </div>
                            <div class="spiralengine-breakdown-item">
                                <span class="spiralengine-breakdown-label"><?php _e('Physical Activation:', 'spiralengine'); ?></span>
                                <span class="spiralengine-breakdown-value" id="review-physical-activation">0</span>
                            </div>
                            <div class="spiralengine-breakdown-item">
                                <span class="spiralengine-breakdown-label"><?php _e('Time Distortion:', 'spiralengine'); ?></span>
                                <span class="spiralengine-breakdown-value" id="review-time-distortion">0</span>
                            </div>
                            <div class="spiralengine-breakdown-item">
                                <span class="spiralengine-breakdown-label"><?php _e('Compulsion:', 'spiralengine'); ?></span>
                                <span class="spiralengine-breakdown-value" id="review-compulsion">0</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Crisis Intervention Message (shown if score >= 13) -->
                <div class="spiralengine-crisis-message" style="display: none;">
                    <div class="spiralengine-crisis-icon">⚠️</div>
                    <h5><?php _e('Important Notice', 'spiralengine'); ?></h5>
                    <p><?php _e('Your assessment indicates you may benefit from immediate support. We strongly encourage you to reach out to a mental health professional or crisis support service.', 'spiralengine'); ?></p>
                    <div class="spiralengine-crisis-resources">
                        <a href="tel:988" class="spiralengine-crisis-link">
                            <?php _e('Call 988 (Crisis Lifeline)', 'spiralengine'); ?>
                        </a>
                        <a href="https://www.crisistextline.org/" target="_blank" class="spiralengine-crisis-link">
                            <?php _e('Crisis Text Line', 'spiralengine'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Membership Assignment -->
        <div class="spiralengine-review-section">
            <h4 class="spiralengine-review-section-title">
                <?php _e('Your Membership', 'spiralengine'); ?>
            </h4>
            
            <div class="spiralengine-review-content">
                <div class="spiralengine-membership-card">
                    <div class="spiralengine-membership-icon">🌟</div>
                    <h5 class="spiralengine-membership-name"><?php _e('Discovery Membership', 'spiralengine'); ?></h5>
                    <p class="spiralengine-membership-description">
                        <?php _e('Free lifetime access to essential tracking tools, basic resources, and our supportive community.', 'spiralengine'); ?>
                    </p>
                    <div class="spiralengine-membership-features">
                        <div class="spiralengine-feature-item">
                            <span class="spiralengine-feature-check">✓</span>
                            <?php _e('Daily SPIRAL tracking', 'spiralengine'); ?>
                        </div>
                        <div class="spiralengine-feature-item">
                            <span class="spiralengine-feature-check">✓</span>
                            <?php _e('Personal dashboard', 'spiralengine'); ?>
                        </div>
                        <div class="spiralengine-feature-item">
                            <span class="spiralengine-feature-check">✓</span>
                            <?php _e('Basic resources library', 'spiralengine'); ?>
                        </div>
                        <div class="spiralengine-feature-item">
                            <span class="spiralengine-feature-check">✓</span>
                            <?php _e('Community support', 'spiralengine'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Privacy & Consent -->
        <div class="spiralengine-review-section">
            <h4 class="spiralengine-review-section-title">
                <?php _e('Privacy & Consent', 'spiralengine'); ?>
            </h4>
            
            <div class="spiralengine-review-content">
                <div class="spiralengine-consent-items">
                    <div class="spiralengine-consent-item">
                        <label class="spiralengine-checkbox-label">
                            <input type="checkbox" 
                                   id="spiralengine-privacy-consent" 
                                   name="privacy_consent" 
                                   class="spiralengine-checkbox" 
                                   required>
                            <span class="spiralengine-checkbox-custom"></span>
                            <span class="spiralengine-checkbox-text">
                                <?php 
                                printf(
                                    __('I agree to the %sPrivacy Policy%s and %sTerms of Service%s', 'spiralengine'),
                                    '<a href="#" target="_blank">', '</a>',
                                    '<a href="#" target="_blank">', '</a>'
                                ); 
                                ?>
                            </span>
                        </label>
                    </div>
                    
                    <div class="spiralengine-consent-item">
                        <label class="spiralengine-checkbox-label">
                            <input type="checkbox" 
                                   id="spiralengine-health-consent" 
                                   name="health_consent" 
                                   class="spiralengine-checkbox" 
                                   required>
                            <span class="spiralengine-checkbox-custom"></span>
                            <span class="spiralengine-checkbox-text">
                                <?php _e('I understand that my health-related data will be encrypted and stored securely, and I can request deletion at any time', 'spiralengine'); ?>
                            </span>
                        </label>
                    </div>
                    
                    <div class="spiralengine-consent-item">
                        <label class="spiralengine-checkbox-label">
                            <input type="checkbox" 
                                   id="spiralengine-email-consent" 
                                   name="email_consent" 
                                   class="spiralengine-checkbox">
                            <span class="spiralengine-checkbox-custom"></span>
                            <span class="spiralengine-checkbox-text">
                                <?php _e('Send me helpful tips and updates about my wellness journey (you can unsubscribe anytime)', 'spiralengine'); ?>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="spiralengine-privacy-notice">
                    <p><?php _e('Your data is encrypted and protected. We never share your personal information with third parties. You maintain full control over your data and can download or delete it at any time from your account settings.', 'spiralengine'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- reCAPTCHA (if enabled) -->
        <?php if (get_option('spiralengine_recaptcha_site_key')): ?>
        <div class="spiralengine-recaptcha-container">
            <div id="spiralengine-recaptcha"></div>
        </div>
        <?php endif; ?>
        
        <!-- Error Messages -->
        <div class="spiralengine-form-messages">
            <div class="spiralengine-error-message" style="display: none;"></div>
            <div class="spiralengine-success-message" style="display: none;"></div>
        </div>
    </div>
    
    <div class="spiralengine-step-navigation">
        <button type="button" class="spiralengine-button spiralengine-button-secondary spiralengine-prev-step" data-prev-step="2">
            <span class="spiralengine-button-icon">←</span>
            <?php _e('Back', 'spiralengine'); ?>
        </button>
        
        <button type="submit" class="spiralengine-button spiralengine-button-primary spiralengine-button-large spiralengine-submit-button">
            <span class="spiralengine-button-text"><?php _e('Complete Registration', 'spiralengine'); ?></span>
            <span class="spiralengine-button-loading" style="display: none;">
                <span class="spiralengine-spinner"></span>
                <?php _e('Creating your account...', 'spiralengine'); ?>
            </span>
        </button>
    </div>
</div>

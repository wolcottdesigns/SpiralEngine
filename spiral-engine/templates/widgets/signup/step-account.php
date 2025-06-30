<?php
// templates/widgets/signup/step-account.php

/**
 * Signup Widget - Step 1: Account Creation
 * 
 * Collects basic account information with real-time validation
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="spiralengine-step spiralengine-step-1 active" data-step="1">
    <h3 class="spiralengine-step-title"><?php _e('Create Your Account', 'spiralengine'); ?></h3>
    <p class="spiralengine-step-description"><?php _e('Start your journey with just a few details', 'spiralengine'); ?></p>
    
    <div class="spiralengine-form-group">
        <label for="spiralengine-username" class="spiralengine-label required">
            <?php _e('Username', 'spiralengine'); ?>
        </label>
        <input type="text" 
               id="spiralengine-username" 
               name="username" 
               class="spiralengine-input" 
               required 
               minlength="3" 
               maxlength="20"
               placeholder="<?php esc_attr_e('Choose a username (3-20 characters)', 'spiralengine'); ?>">
        <div class="spiralengine-field-feedback">
            <span class="spiralengine-field-status"></span>
            <span class="spiralengine-field-message"></span>
        </div>
    </div>
    
    <div class="spiralengine-form-group">
        <label for="spiralengine-email" class="spiralengine-label required">
            <?php _e('Email Address', 'spiralengine'); ?>
        </label>
        <input type="email" 
               id="spiralengine-email" 
               name="email" 
               class="spiralengine-input" 
               required
               placeholder="<?php esc_attr_e('your@email.com', 'spiralengine'); ?>">
        <div class="spiralengine-field-feedback">
            <span class="spiralengine-field-status"></span>
            <span class="spiralengine-field-message"></span>
        </div>
    </div>
    
    <div class="spiralengine-form-row">
        <div class="spiralengine-form-group spiralengine-form-half">
            <label for="spiralengine-password" class="spiralengine-label required">
                <?php _e('Password', 'spiralengine'); ?>
            </label>
            <input type="password" 
                   id="spiralengine-password" 
                   name="password" 
                   class="spiralengine-input" 
                   required 
                   minlength="8"
                   placeholder="<?php esc_attr_e('Minimum 8 characters', 'spiralengine'); ?>">
            <div class="spiralengine-password-strength">
                <div class="spiralengine-password-strength-bar">
                    <div class="spiralengine-password-strength-fill"></div>
                </div>
                <span class="spiralengine-password-strength-text"></span>
            </div>
        </div>
        
        <div class="spiralengine-form-group spiralengine-form-half">
            <label for="spiralengine-password-confirm" class="spiralengine-label required">
                <?php _e('Confirm Password', 'spiralengine'); ?>
            </label>
            <input type="password" 
                   id="spiralengine-password-confirm" 
                   name="password_confirm" 
                   class="spiralengine-input" 
                   required
                   placeholder="<?php esc_attr_e('Re-enter password', 'spiralengine'); ?>">
            <div class="spiralengine-field-feedback">
                <span class="spiralengine-field-status"></span>
                <span class="spiralengine-field-message"></span>
            </div>
        </div>
    </div>
    
    <div class="spiralengine-form-divider"></div>
    
    <div class="spiralengine-form-group">
        <label class="spiralengine-label">
            <?php _e('Gender', 'spiralengine'); ?>
            <span class="spiralengine-label-help"><?php _e('(Helps us provide relevant wellness tracking options)', 'spiralengine'); ?></span>
        </label>
        <div class="spiralengine-radio-cards">
            <label class="spiralengine-radio-card">
                <input type="radio" name="gender" value="female" class="spiralengine-radio-input">
                <div class="spiralengine-radio-card-content">
                    <span class="spiralengine-radio-icon">👩</span>
                    <span class="spiralengine-radio-label"><?php _e('Female', 'spiralengine'); ?></span>
                </div>
            </label>
            
            <label class="spiralengine-radio-card">
                <input type="radio" name="gender" value="male" class="spiralengine-radio-input">
                <div class="spiralengine-radio-card-content">
                    <span class="spiralengine-radio-icon">👨</span>
                    <span class="spiralengine-radio-label"><?php _e('Male', 'spiralengine'); ?></span>
                </div>
            </label>
            
            <label class="spiralengine-radio-card">
                <input type="radio" name="gender" value="other" class="spiralengine-radio-input">
                <div class="spiralengine-radio-card-content">
                    <span class="spiralengine-radio-icon">👤</span>
                    <span class="spiralengine-radio-label"><?php _e('Other/Prefer not to say', 'spiralengine'); ?></span>
                </div>
            </label>
        </div>
        <p class="spiralengine-field-note">
            <?php _e('You can change this anytime in your profile settings', 'spiralengine'); ?>
        </p>
    </div>
    
    <!-- Conditional wellness tracking option -->
    <div class="spiralengine-form-group spiralengine-wellness-tracking-option" style="display: none;">
        <div class="spiralengine-checkbox-wrapper">
            <label class="spiralengine-checkbox-label">
                <input type="checkbox" 
                       id="spiralengine-track-wellness" 
                       name="track_wellness" 
                       class="spiralengine-checkbox">
                <span class="spiralengine-checkbox-custom"></span>
                <span class="spiralengine-checkbox-text">
                    <?php _e('I\'d like to track personalized wellness data for better insights', 'spiralengine'); ?>
                </span>
            </label>
            <p class="spiralengine-field-note">
                <?php _e('This enables advanced pattern recognition and personalized recommendations based on your unique biological rhythms.', 'spiralengine'); ?>
            </p>
        </div>
    </div>
    
    <div class="spiralengine-form-divider"></div>
    
    <h4 class="spiralengine-section-title"><?php _e('Optional Information', 'spiralengine'); ?></h4>
    
    <div class="spiralengine-form-row">
        <div class="spiralengine-form-group spiralengine-form-half">
            <label for="spiralengine-first-name" class="spiralengine-label">
                <?php _e('First Name', 'spiralengine'); ?>
            </label>
            <input type="text" 
                   id="spiralengine-first-name" 
                   name="first_name" 
                   class="spiralengine-input"
                   placeholder="<?php esc_attr_e('Your first name', 'spiralengine'); ?>">
        </div>
        
        <div class="spiralengine-form-group spiralengine-form-half">
            <label for="spiralengine-last-name" class="spiralengine-label">
                <?php _e('Last Name', 'spiralengine'); ?>
            </label>
            <input type="text" 
                   id="spiralengine-last-name" 
                   name="last_name" 
                   class="spiralengine-input"
                   placeholder="<?php esc_attr_e('Your last name', 'spiralengine'); ?>">
        </div>
    </div>
    
    <div class="spiralengine-form-row">
        <div class="spiralengine-form-group spiralengine-form-half">
            <label for="spiralengine-country" class="spiralengine-label">
                <?php _e('Country', 'spiralengine'); ?>
            </label>
            <select id="spiralengine-country" name="country" class="spiralengine-select">
                <option value=""><?php _e('Select country', 'spiralengine'); ?></option>
                <option value="US"><?php _e('United States', 'spiralengine'); ?></option>
                <option value="CA"><?php _e('Canada', 'spiralengine'); ?></option>
                <option value="GB"><?php _e('United Kingdom', 'spiralengine'); ?></option>
                <option value="AU"><?php _e('Australia', 'spiralengine'); ?></option>
                <!-- Add more countries as needed -->
            </select>
        </div>
        
        <div class="spiralengine-form-group spiralengine-form-half spiralengine-state-group" style="display: none;">
            <label for="spiralengine-state" class="spiralengine-label">
                <?php _e('State', 'spiralengine'); ?>
            </label>
            <select id="spiralengine-state" name="state" class="spiralengine-select">
                <option value=""><?php _e('Select state', 'spiralengine'); ?></option>
                <option value="AL"><?php _e('Alabama', 'spiralengine'); ?></option>
                <option value="AK"><?php _e('Alaska', 'spiralengine'); ?></option>
                <option value="AZ"><?php _e('Arizona', 'spiralengine'); ?></option>
                <option value="AR"><?php _e('Arkansas', 'spiralengine'); ?></option>
                <option value="CA"><?php _e('California', 'spiralengine'); ?></option>
                <!-- Add all US states -->
            </select>
        </div>
    </div>
    
    <div class="spiralengine-step-navigation">
        <button type="button" class="spiralengine-button spiralengine-button-primary spiralengine-next-step" data-next-step="2">
            <?php _e('Continue to Assessment', 'spiralengine'); ?>
            <span class="spiralengine-button-icon">→</span>
        </button>
    </div>
</div>

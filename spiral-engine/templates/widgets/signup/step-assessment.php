<?php
// templates/widgets/signup/step-assessment.php

/**
 * Signup Widget - Step 2: SPIRAL Assessment
 * 
 * The 6 Sacred Questions with 0-3 scale responses
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="spiralengine-step spiralengine-step-2" data-step="2">
    <h3 class="spiralengine-step-title"><?php _e('SPIRAL Assessment', 'spiralengine'); ?></h3>
    <p class="spiralengine-step-description"><?php _e('Help us understand your current state by answering these 6 questions', 'spiralengine'); ?></p>
    
    <div class="spiralengine-assessment-container">
        <div class="spiralengine-assessment-progress">
            <div class="spiralengine-assessment-progress-bar">
                <div class="spiralengine-assessment-progress-fill" style="width: 0%;"></div>
            </div>
            <div class="spiralengine-assessment-progress-text">
                <span class="spiralengine-current-question">1</span> / <span class="spiralengine-total-questions">6</span>
            </div>
        </div>
        
        <!-- Question 1: Acceleration -->
        <div class="spiralengine-assessment-question active" data-question="1">
            <h4 class="spiralengine-question-title">
                <?php _e('How much are you experiencing feelings of acceleration or speeding up?', 'spiralengine'); ?>
            </h4>
            <p class="spiralengine-question-description">
                <?php _e('Racing thoughts, feeling like everything is moving too fast', 'spiralengine'); ?>
            </p>
            
            <div class="spiralengine-scale-options">
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_acceleration" value="0" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">0</span>
                        <span class="spiralengine-scale-label"><?php _e('Not at all', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_acceleration" value="1" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">1</span>
                        <span class="spiralengine-scale-label"><?php _e('A little', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_acceleration" value="2" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">2</span>
                        <span class="spiralengine-scale-label"><?php _e('Moderately', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_acceleration" value="3" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">3</span>
                        <span class="spiralengine-scale-label"><?php _e('Extremely', 'spiralengine'); ?></span>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- Question 2: Catastrophizing -->
        <div class="spiralengine-assessment-question" data-question="2">
            <h4 class="spiralengine-question-title">
                <?php _e('How much are you having catastrophizing or worst-case scenario thoughts?', 'spiralengine'); ?>
            </h4>
            <p class="spiralengine-question-description">
                <?php _e('Imagining terrible outcomes, expecting the worst', 'spiralengine'); ?>
            </p>
            
            <div class="spiralengine-scale-options">
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_catastrophizing" value="0" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">0</span>
                        <span class="spiralengine-scale-label"><?php _e('Not at all', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_catastrophizing" value="1" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">1</span>
                        <span class="spiralengine-scale-label"><?php _e('A little', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_catastrophizing" value="2" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">2</span>
                        <span class="spiralengine-scale-label"><?php _e('Moderately', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_catastrophizing" value="3" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">3</span>
                        <span class="spiralengine-scale-label"><?php _e('Extremely', 'spiralengine'); ?></span>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- Question 3: Loss of Control -->
        <div class="spiralengine-assessment-question" data-question="3">
            <h4 class="spiralengine-question-title">
                <?php _e('How much do you feel a loss of control?', 'spiralengine'); ?>
            </h4>
            <p class="spiralengine-question-description">
                <?php _e('Feeling powerless, unable to influence outcomes', 'spiralengine'); ?>
            </p>
            
            <div class="spiralengine-scale-options">
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_loss_of_control" value="0" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">0</span>
                        <span class="spiralengine-scale-label"><?php _e('Not at all', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_loss_of_control" value="1" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">1</span>
                        <span class="spiralengine-scale-label"><?php _e('A little', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_loss_of_control" value="2" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">2</span>
                        <span class="spiralengine-scale-label"><?php _e('Moderately', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_loss_of_control" value="3" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">3</span>
                        <span class="spiralengine-scale-label"><?php _e('Extremely', 'spiralengine'); ?></span>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- Question 4: Physical Activation -->
        <div class="spiralengine-assessment-question" data-question="4">
            <h4 class="spiralengine-question-title">
                <?php _e('How much physical activation are you experiencing?', 'spiralengine'); ?>
            </h4>
            <p class="spiralengine-question-description">
                <?php _e('Heart racing, sweating, tension, restlessness, shaking', 'spiralengine'); ?>
            </p>
            
            <div class="spiralengine-scale-options">
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_physical_activation" value="0" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">0</span>
                        <span class="spiralengine-scale-label"><?php _e('Not at all', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_physical_activation" value="1" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">1</span>
                        <span class="spiralengine-scale-label"><?php _e('A little', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_physical_activation" value="2" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">2</span>
                        <span class="spiralengine-scale-label"><?php _e('Moderately', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_physical_activation" value="3" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">3</span>
                        <span class="spiralengine-scale-label"><?php _e('Extremely', 'spiralengine'); ?></span>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- Question 5: Time Distortion -->
        <div class="spiralengine-assessment-question" data-question="5">
            <h4 class="spiralengine-question-title">
                <?php _e('How much time distortion are you experiencing?', 'spiralengine'); ?>
            </h4>
            <p class="spiralengine-question-description">
                <?php _e('Time feeling stretched or compressed, losing track of time', 'spiralengine'); ?>
            </p>
            
            <div class="spiralengine-scale-options">
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_time_distortion" value="0" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">0</span>
                        <span class="spiralengine-scale-label"><?php _e('Not at all', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_time_distortion" value="1" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">1</span>
                        <span class="spiralengine-scale-label"><?php _e('A little', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_time_distortion" value="2" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">2</span>
                        <span class="spiralengine-scale-label"><?php _e('Moderately', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_time_distortion" value="3" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">3</span>
                        <span class="spiralengine-scale-label"><?php _e('Extremely', 'spiralengine'); ?></span>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- Question 6: Compulsion -->
        <div class="spiralengine-assessment-question" data-question="6">
            <h4 class="spiralengine-question-title">
                <?php _e('How strong is your compulsion to act or do something?', 'spiralengine'); ?>
            </h4>
            <p class="spiralengine-question-description">
                <?php _e('Urgent need to take action, fix things, or escape', 'spiralengine'); ?>
            </p>
            
            <div class="spiralengine-scale-options">
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_compulsion" value="0" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">0</span>
                        <span class="spiralengine-scale-label"><?php _e('Not at all', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_compulsion" value="1" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">1</span>
                        <span class="spiralengine-scale-label"><?php _e('A little', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_compulsion" value="2" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">2</span>
                        <span class="spiralengine-scale-label"><?php _e('Moderately', 'spiralengine'); ?></span>
                    </div>
                </label>
                
                <label class="spiralengine-scale-option">
                    <input type="radio" name="spiral_compulsion" value="3" required>
                    <div class="spiralengine-scale-card">
                        <span class="spiralengine-scale-value">3</span>
                        <span class="spiralengine-scale-label"><?php _e('Extremely', 'spiralengine'); ?></span>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- Assessment Summary (shown after all questions answered) -->
        <div class="spiralengine-assessment-summary" style="display: none;">
            <div class="spiralengine-score-display">
                <h4><?php _e('Your Current Intensity Level', 'spiralengine'); ?></h4>
                <div class="spiralengine-score-meter">
                    <div class="spiralengine-score-value">
                        <span class="spiralengine-score-number">0</span>
                        <span class="spiralengine-score-max">/ 18</span>
                    </div>
                    <div class="spiralengine-intensity-indicator">
                        <span class="spiralengine-intensity-text"></span>
                    </div>
                </div>
                <p class="spiralengine-score-description">
                    <?php _e('This score helps us personalize your experience and provide the most relevant resources for your journey.', 'spiralengine'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="spiralengine-step-navigation">
        <button type="button" class="spiralengine-button spiralengine-button-secondary spiralengine-prev-step" data-prev-step="1">
            <span class="spiralengine-button-icon">←</span>
            <?php _e('Back', 'spiralengine'); ?>
        </button>
        
        <button type="button" class="spiralengine-button spiralengine-button-primary spiralengine-next-question" style="display: none;">
            <?php _e('Next Question', 'spiralengine'); ?>
            <span class="spiralengine-button-icon">→</span>
        </button>
        
        <button type="button" class="spiralengine-button spiralengine-button-primary spiralengine-next-step" data-next-step="3" style="display: none;">
            <?php _e('Continue to Review', 'spiralengine'); ?>
            <span class="spiralengine-button-icon">→</span>
        </button>
    </div>
</div>

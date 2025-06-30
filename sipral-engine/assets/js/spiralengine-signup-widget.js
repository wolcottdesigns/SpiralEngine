// assets/js/spiralengine-signup-widget.js

/**
 * Spiral Engine Signup Widget JavaScript
 * 
 * Handles all interactive functionality for the three-step signup process
 */

(function($) {
    'use strict';
    
    // Main signup widget controller
    const SpiralEngineSignup = {
        
        // Current state
        currentStep: 1,
        currentQuestion: 1,
        formData: {},
        assessmentScores: {},
        validationStates: {
            username: false,
            email: false,
            password: false,
            passwordConfirm: false
        },
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.initializeForm();
        },
        
        // Bind all events
        bindEvents: function() {
            // Step navigation
            $(document).on('click', '.spiralengine-next-step', this.handleNextStep.bind(this));
            $(document).on('click', '.spiralengine-prev-step', this.handlePrevStep.bind(this));
            $(document).on('click', '.spiralengine-edit-section', this.handleEditSection.bind(this));
            
            // Real-time validation
            $('#spiralengine-username').on('blur', this.validateUsername.bind(this));
            $('#spiralengine-email').on('blur', this.validateEmail.bind(this));
            $('#spiralengine-password').on('input', this.validatePassword.bind(this));
            $('#spiralengine-password-confirm').on('input', this.validatePasswordConfirm.bind(this));
            
            // Gender selection and wellness tracking
            $('input[name="gender"]').on('change', this.handleGenderChange.bind(this));
            
            // Country/State selection
            $('#spiralengine-country').on('change', this.handleCountryChange.bind(this));
            
            // Assessment questions
            $(document).on('click', '.spiralengine-scale-option input', this.handleAssessmentAnswer.bind(this));
            $(document).on('click', '.spiralengine-next-question', this.handleNextQuestion.bind(this));
            
            // Form submission
            $('#spiralengine-signup-form').on('submit', this.handleSubmit.bind(this));
            
            // Skip animation link
            $(document).on('click', '.spiralengine-skip-link', this.skipAnimation.bind(this));
        },
        
        // Initialize form
        initializeForm: function() {
            // Focus first field
            $('#spiralengine-username').focus();
            
            // Initialize reCAPTCHA if enabled
            if (typeof grecaptcha !== 'undefined' && $('#spiralengine-recaptcha').length) {
                grecaptcha.render('spiralengine-recaptcha', {
                    'sitekey': spiralengine_signup.recaptcha_site_key
                });
            }
        },
        
        // Handle next step
        handleNextStep: function(e) {
            e.preventDefault();
            
            const nextStep = parseInt($(e.currentTarget).data('next-step'));
            
            // Validate current step
            if (!this.validateStep(this.currentStep)) {
                return;
            }
            
            // Save current step data
            this.saveStepData(this.currentStep);
            
            // Move to next step
            this.goToStep(nextStep);
        },
        
        // Handle previous step
        handlePrevStep: function(e) {
            e.preventDefault();
            
            const prevStep = parseInt($(e.currentTarget).data('prev-step'));
            this.goToStep(prevStep);
        },
        
        // Handle edit section
        handleEditSection: function(e) {
            e.preventDefault();
            
            const editStep = parseInt($(e.currentTarget).data('edit-step'));
            this.goToStep(editStep);
        },
        
        // Go to specific step
        goToStep: function(step) {
            // Hide all steps
            $('.spiralengine-step').removeClass('active');
            
            // Show target step
            $(`.spiralengine-step-${step}`).addClass('active');
            
            // Update progress
            this.updateProgress(step);
            
            // Update current step
            this.currentStep = step;
            
            // Special handling for assessment step
            if (step === 2) {
                this.resetAssessment();
            }
            
            // Special handling for review step
            if (step === 3) {
                this.populateReview();
            }
            
            // Scroll to top
            $('html, body').animate({ scrollTop: $('.spiralengine-signup-container').offset().top - 100 }, 300);
        },
        
        // Update progress bar
        updateProgress: function(step) {
            const progress = (step / 3) * 100;
            $('.spiralengine-progress-fill').css('width', progress + '%');
            
            // Update step indicators
            $('.spiralengine-progress-step').removeClass('active completed');
            for (let i = 1; i <= step; i++) {
                $(`.spiralengine-progress-step[data-step="${i}"]`).addClass(i < step ? 'completed' : 'active');
            }
        },
        
        // Validate current step
        validateStep: function(step) {
            if (step === 1) {
                // Validate account fields
                const username = $('#spiralengine-username').val();
                const email = $('#spiralengine-email').val();
                const password = $('#spiralengine-password').val();
                const passwordConfirm = $('#spiralengine-password-confirm').val();
                
                if (!username || !email || !password || !passwordConfirm) {
                    this.showError('Please fill in all required fields');
                    return false;
                }
                
                if (!this.validationStates.username || !this.validationStates.email || 
                    !this.validationStates.password || !this.validationStates.passwordConfirm) {
                    this.showError('Please correct the errors before continuing');
                    return false;
                }
                
                return true;
            }
            
            if (step === 2) {
                // Check all assessment questions answered
                const totalQuestions = 6;
                let answeredQuestions = 0;
                
                $('.spiralengine-assessment-question').each(function() {
                    if ($(this).find('input:checked').length > 0) {
                        answeredQuestions++;
                    }
                });
                
                if (answeredQuestions < totalQuestions) {
                    this.showError('Please answer all assessment questions');
                    return false;
                }
                
                return true;
            }
            
            return true;
        },
        
        // Save step data
        saveStepData: function(step) {
            if (step === 1) {
                this.formData.username = $('#spiralengine-username').val();
                this.formData.email = $('#spiralengine-email').val();
                this.formData.password = $('#spiralengine-password').val();
                this.formData.first_name = $('#spiralengine-first-name').val();
                this.formData.last_name = $('#spiralengine-last-name').val();
                this.formData.gender = $('input[name="gender"]:checked').val() || '';
                this.formData.track_wellness = $('#spiralengine-track-wellness').is(':checked');
                this.formData.country = $('#spiralengine-country').val();
                this.formData.state = $('#spiralengine-state').val();
            }
            
            if (step === 2) {
                this.assessmentScores = {
                    acceleration: parseInt($('input[name="spiral_acceleration"]:checked').val()) || 0,
                    catastrophizing: parseInt($('input[name="spiral_catastrophizing"]:checked').val()) || 0,
                    loss_of_control: parseInt($('input[name="spiral_loss_of_control"]:checked').val()) || 0,
                    physical_activation: parseInt($('input[name="spiral_physical_activation"]:checked').val()) || 0,
                    time_distortion: parseInt($('input[name="spiral_time_distortion"]:checked').val()) || 0,
                    compulsion: parseInt($('input[name="spiral_compulsion"]:checked').val()) || 0
                };
            }
        },
        
        // Username validation
        validateUsername: function() {
            const username = $('#spiralengine-username').val();
            const $field = $('#spiralengine-username');
            const $feedback = $field.siblings('.spiralengine-field-feedback');
            
            if (username.length < 3 || username.length > 20) {
                this.showFieldError($field, $feedback, 'Username must be 3-20 characters');
                this.validationStates.username = false;
                return;
            }
            
            // Show checking status
            this.showFieldStatus($field, $feedback, 'checking', spiralengine_signup.messages.username_checking);
            
            // AJAX check
            $.ajax({
                url: spiralengine_signup.ajax_url,
                type: 'POST',
                data: {
                    action: 'spiralengine_check_username',
                    username: username,
                    nonce: spiralengine_signup.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showFieldSuccess($field, $feedback, response.data.message);
                        this.validationStates.username = true;
                    } else {
                        this.showFieldError($field, $feedback, response.data.message);
                        this.validationStates.username = false;
                    }
                }
            });
        },
        
        // Email validation
        validateEmail: function() {
            const email = $('#spiralengine-email').val();
            const $field = $('#spiralengine-email');
            const $feedback = $field.siblings('.spiralengine-field-feedback');
            
            if (!this.isValidEmail(email)) {
                this.showFieldError($field, $feedback, 'Please enter a valid email address');
                this.validationStates.email = false;
                return;
            }
            
            // Show checking status
            this.showFieldStatus($field, $feedback, 'checking', spiralengine_signup.messages.email_checking);
            
            // AJAX check
            $.ajax({
                url: spiralengine_signup.ajax_url,
                type: 'POST',
                data: {
                    action: 'spiralengine_check_email',
                    email: email,
                    nonce: spiralengine_signup.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showFieldSuccess($field, $feedback, response.data.message);
                        this.validationStates.email = true;
                    } else {
                        this.showFieldError($field, $feedback, response.data.message);
                        this.validationStates.email = false;
                    }
                }
            });
        },
        
        // Password validation
        validatePassword: function() {
            const password = $('#spiralengine-password').val();
            const $field = $('#spiralengine-password');
            const $strengthBar = $('.spiralengine-password-strength');
            
            if (password.length < 8) {
                $strengthBar.find('.spiralengine-password-strength-text').text('Too short');
                $strengthBar.find('.spiralengine-password-strength-fill').css('width', '20%').removeClass('medium strong').addClass('weak');
                this.validationStates.password = false;
                return;
            }
            
            // Calculate password strength
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            const strengthLevels = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
            const strengthClasses = ['weak', 'weak', 'medium', 'strong', 'strong'];
            const strengthWidths = ['20%', '40%', '60%', '80%', '100%'];
            
            $strengthBar.find('.spiralengine-password-strength-text').text(strengthLevels[strength - 1]);
            $strengthBar.find('.spiralengine-password-strength-fill')
                .css('width', strengthWidths[strength - 1])
                .removeClass('weak medium strong')
                .addClass(strengthClasses[strength - 1]);
            
            this.validationStates.password = true;
            
            // Revalidate confirm if it has a value
            if ($('#spiralengine-password-confirm').val()) {
                this.validatePasswordConfirm();
            }
        },
        
        // Password confirmation validation
        validatePasswordConfirm: function() {
            const password = $('#spiralengine-password').val();
            const passwordConfirm = $('#spiralengine-password-confirm').val();
            const $field = $('#spiralengine-password-confirm');
            const $feedback = $field.siblings('.spiralengine-field-feedback');
            
            if (passwordConfirm !== password) {
                this.showFieldError($field, $feedback, 'Passwords do not match');
                this.validationStates.passwordConfirm = false;
            } else if (passwordConfirm) {
                this.showFieldSuccess($field, $feedback, 'Passwords match');
                this.validationStates.passwordConfirm = true;
            }
        },
        
        // Handle gender change
        handleGenderChange: function(e) {
            const selectedGender = $(e.currentTarget).val();
            
            if (selectedGender === 'female') {
                $('.spiralengine-wellness-tracking-option').slideDown(200);
            } else {
                $('.spiralengine-wellness-tracking-option').slideUp(200);
                $('#spiralengine-track-wellness').prop('checked', false);
            }
        },
        
        // Handle country change
        handleCountryChange: function(e) {
            const country = $(e.currentTarget).val();
            
            if (country === 'US') {
                $('.spiralengine-state-group').slideDown(200);
            } else {
                $('.spiralengine-state-group').slideUp(200);
                $('#spiralengine-state').val('');
            }
        },
        
        // Handle assessment answer
        handleAssessmentAnswer: function(e) {
            const $question = $(e.currentTarget).closest('.spiralengine-assessment-question');
            const questionNumber = parseInt($question.data('question'));
            
            // Update visual selection
            $question.find('.spiralengine-scale-option').removeClass('selected');
            $(e.currentTarget).closest('.spiralengine-scale-option').addClass('selected');
            
            // Auto-advance to next question
            if (questionNumber < 6) {
                setTimeout(() => {
                    this.handleNextQuestion();
                }, 300);
            } else {
                // All questions answered, show summary
                this.showAssessmentSummary();
            }
        },
        
        // Handle next question
        handleNextQuestion: function() {
            if (this.currentQuestion < 6) {
                // Hide current question
                $(`.spiralengine-assessment-question[data-question="${this.currentQuestion}"]`).removeClass('active');
                
                // Show next question
                this.currentQuestion++;
                $(`.spiralengine-assessment-question[data-question="${this.currentQuestion}"]`).addClass('active');
                
                // Update progress
                const progress = (this.currentQuestion / 6) * 100;
                $('.spiralengine-assessment-progress-fill').css('width', progress + '%');
                $('.spiralengine-current-question').text(this.currentQuestion);
                
                // Update navigation
                if (this.currentQuestion === 6) {
                    $('.spiralengine-next-question').hide();
                }
            }
        },
        
        // Reset assessment
        resetAssessment: function() {
            this.currentQuestion = 1;
            $('.spiralengine-assessment-question').removeClass('active');
            $(`.spiralengine-assessment-question[data-question="1"]`).addClass('active');
            $('.spiralengine-assessment-progress-fill').css('width', '16.67%');
            $('.spiralengine-current-question').text('1');
            $('.spiralengine-next-question').show();
            $('.spiralengine-next-step[data-next-step="3"]').hide();
            $('.spiralengine-assessment-summary').hide();
        },
        
        // Show assessment summary
        showAssessmentSummary: function() {
            // Calculate total score
            this.saveStepData(2);
            const totalScore = Object.values(this.assessmentScores).reduce((a, b) => a + b, 0);
            
            // Determine intensity
            let intensity = 'Low';
            let intensityClass = 'low';
            if (totalScore > 6) {
                intensity = 'Medium';
                intensityClass = 'medium';
            }
            if (totalScore > 12) {
                intensity = 'High';
                intensityClass = 'high';
            }
            
            // Update summary
            $('.spiralengine-score-number').text(totalScore);
            $('.spiralengine-intensity-text').text(intensity).removeClass('low medium high').addClass(intensityClass);
            
            // Show summary
            $('.spiralengine-assessment-summary').fadeIn(300);
            $('.spiralengine-next-step[data-next-step="3"]').show();
            
            // Hide question navigation
            $('.spiralengine-next-question').hide();
        },
        
        // Populate review step
        populateReview: function() {
            // Account information
            $('#review-username').text(this.formData.username);
            $('#review-email').text(this.formData.email);
            
            const fullName = [this.formData.first_name, this.formData.last_name].filter(Boolean).join(' ') || 'Not provided';
            $('#review-name').text(fullName);
            
            const genderLabels = {
                'female': 'Female',
                'male': 'Male',
                'other': 'Other/Prefer not to say'
            };
            $('#review-gender').text(genderLabels[this.formData.gender] || 'Not specified');
            
            // Show wellness tracking if applicable
            if (this.formData.gender === 'female' && this.formData.track_wellness) {
                $('.spiralengine-wellness-review').show();
            } else {
                $('.spiralengine-wellness-review').hide();
            }
            
            // Assessment results
            const totalScore = Object.values(this.assessmentScores).reduce((a, b) => a + b, 0);
            $('#review-score').text(totalScore);
            
            let intensity = 'Low';
            let intensityClass = 'low';
            if (totalScore > 6) {
                intensity = 'Medium';
                intensityClass = 'medium';
            }
            if (totalScore > 12) {
                intensity = 'High';
                intensityClass = 'high';
            }
            
            $('#review-intensity').text(intensity).removeClass('low medium high').addClass(intensityClass);
            
            // Score breakdown
            $('#review-acceleration').text(this.assessmentScores.acceleration);
            $('#review-catastrophizing').text(this.assessmentScores.catastrophizing);
            $('#review-loss-of-control').text(this.assessmentScores.loss_of_control);
            $('#review-physical-activation').text(this.assessmentScores.physical_activation);
            $('#review-time-distortion').text(this.assessmentScores.time_distortion);
            $('#review-compulsion').text(this.assessmentScores.compulsion);
            
            // Show crisis message if score >= 13
            if (totalScore >= 13) {
                $('.spiralengine-crisis-message').show();
            } else {
                $('.spiralengine-crisis-message').hide();
            }
        },
        
        // Handle form submission
        handleSubmit: function(e) {
            e.preventDefault();
            
            // Validate consent checkboxes
            if (!$('#spiralengine-privacy-consent').is(':checked') || 
                !$('#spiralengine-health-consent').is(':checked')) {
                this.showError('Please accept the privacy policy and health data consent');
                return;
            }
            
            // Show loading state
            $('.spiralengine-submit-button').prop('disabled', true);
            $('.spiralengine-button-text').hide();
            $('.spiralengine-button-loading').show();
            
            // Prepare form data
            const submitData = {
                action: 'spiralengine_signup_register',
                nonce: spiralengine_signup.nonce,
                ...this.formData,
                ...this.assessmentScores,
                spiral_acceleration: this.assessmentScores.acceleration,
                spiral_catastrophizing: this.assessmentScores.catastrophizing,
                spiral_loss_of_control: this.assessmentScores.loss_of_control,
                spiral_physical_activation: this.assessmentScores.physical_activation,
                spiral_time_distortion: this.assessmentScores.time_distortion,
                spiral_compulsion: this.assessmentScores.compulsion,
                privacy_consent: $('#spiralengine-privacy-consent').is(':checked'),
                health_consent: $('#spiralengine-health-consent').is(':checked'),
                email_consent: $('#spiralengine-email-consent').is(':checked')
            };
            
            // Add reCAPTCHA if present
            if (typeof grecaptcha !== 'undefined' && $('#spiralengine-recaptcha').length) {
                submitData.recaptcha = grecaptcha.getResponse();
            }
            
            // Submit registration
            $.ajax({
                url: spiralengine_signup.ajax_url,
                type: 'POST',
                data: submitData,
                success: (response) => {
                    if (response.success) {
                        // Show success animation
                        this.showSuccessAnimation(response.data.redirect, response.data.show_crisis);
                    } else {
                        this.showError(response.data.message || spiralengine_signup.messages.error_generic);
                        $('.spiralengine-submit-button').prop('disabled', false);
                        $('.spiralengine-button-text').show();
                        $('.spiralengine-button-loading').hide();
                    }
                },
                error: () => {
                    this.showError(spiralengine_signup.messages.error_generic);
                    $('.spiralengine-submit-button').prop('disabled', false);
                    $('.spiralengine-button-text').show();
                    $('.spiralengine-button-loading').hide();
                }
            });
        },
        
        // Show success animation
        showSuccessAnimation: function(redirectUrl, showCrisis) {
            // Show animation container
            $('.spiralengine-success-animation').fadeIn(300);
            
            // Animation timeline
            setTimeout(() => {
                // Start spiral animation
                $('.spiralengine-spiral-path').css('opacity', '1');
            }, 100);
            
            setTimeout(() => {
                // Show first message
                $('.spiralengine-message-1').fadeIn(300);
                $('.dot-1').addClass('active');
            }, 500);
            
            setTimeout(() => {
                // Transition to second message
                $('.spiralengine-message-1').fadeOut(200, () => {
                    $('.spiralengine-message-2').fadeIn(300);
                    $('.dot-2').addClass('active');
                });
                
                // Start particle animation
                $('.particle').css('opacity', '1');
                
                // Add rotation to spiral
                $('.spiralengine-spiral-logo').addClass('rotating');
            }, 3500);
            
            setTimeout(() => {
                // Transition to final message
                $('.spiralengine-message-2').fadeOut(200, () => {
                    $('.spiralengine-message-3').fadeIn(300);
                    $('.dot-3').addClass('active');
                });
                
                // Trigger confetti
                this.createConfetti();
            }, 8000);
            
            setTimeout(() => {
                // Show redirect notice
                $('.spiralengine-redirect-notice').fadeIn(300);
                $('.spiralengine-skip-link').attr('href', redirectUrl);
                
                // Start countdown
                let countdown = 3;
                const countdownInterval = setInterval(() => {
                    countdown--;
                    $('.countdown').text(countdown);
                    
                    if (countdown === 0) {
                        clearInterval(countdownInterval);
                        window.location.href = redirectUrl;
                    }
                }, 1000);
            }, 12000);
            
            // Show crisis message if needed
            if (showCrisis) {
                setTimeout(() => {
                    alert(spiralengine_signup.messages.crisis_message);
                }, 2000);
            }
        },
        
        // Create confetti effect
        createConfetti: function() {
            const colors = ['#9333EA', '#7C3AED', '#6B46C1', '#5B21B6', '#4C1D95'];
            const $container = $('.spiralengine-confetti-container');
            
            for (let i = 0; i < 50; i++) {
                const $confetti = $('<div class="spiralengine-confetti"></div>');
                $confetti.css({
                    left: Math.random() * 100 + '%',
                    background: colors[Math.floor(Math.random() * colors.length)],
                    animationDelay: Math.random() * 3 + 's',
                    animationDuration: (Math.random() * 2 + 2) + 's'
                });
                $container.append($confetti);
            }
        },
        
        // Skip animation
        skipAnimation: function(e) {
            e.preventDefault();
            window.location.href = $(e.currentTarget).attr('href');
        },
        
        // Helper: Show field error
        showFieldError: function($field, $feedback, message) {
            $field.removeClass('valid').addClass('error');
            $feedback.find('.spiralengine-field-status').html('✗').removeClass('valid checking').addClass('error');
            $feedback.find('.spiralengine-field-message').text(message);
        },
        
        // Helper: Show field success
        showFieldSuccess: function($field, $feedback, message) {
            $field.removeClass('error').addClass('valid');
            $feedback.find('.spiralengine-field-status').html('✓').removeClass('error checking').addClass('valid');
            $feedback.find('.spiralengine-field-message').text(message);
        },
        
        // Helper: Show field status
        showFieldStatus: function($field, $feedback, status, message) {
            $field.removeClass('error valid');
            $feedback.find('.spiralengine-field-status').html('⟳').removeClass('error valid').addClass(status);
            $feedback.find('.spiralengine-field-message').text(message);
        },
        
        // Helper: Show error message
        showError: function(message) {
            $('.spiralengine-error-message').text(message).fadeIn(300);
            setTimeout(() => {
                $('.spiralengine-error-message').fadeOut(300);
            }, 5000);
        },
        
        // Helper: Email validation
        isValidEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        if ($('.spiralengine-signup-container').length) {
            SpiralEngineSignup.init();
        }
    });
    
})(jQuery);

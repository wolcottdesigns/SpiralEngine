<?php
// templates/widgets/signup/success-animation.php

/**
 * Signup Widget - Success Animation
 * 
 * The Blooming Spiral animation sequence
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="spiralengine-success-animation" style="display: none;">
    <div class="spiralengine-animation-container">
        <!-- Spiral Logo Animation -->
        <div class="spiralengine-spiral-wrapper">
            <svg class="spiralengine-spiral-logo" viewBox="0 0 200 200" width="200" height="200">
                <!-- Base Spiral -->
                <path id="spiral-path" d="M100,100 m0,0 a5,5 0 0,1 10,0 a10,10 0 0,1 -20,0 a15,15 0 0,1 30,0 a20,20 0 0,1 -40,0 a25,25 0 0,1 50,0 a30,30 0 0,1 -60,0 a35,35 0 0,1 70,0 a40,40 0 0,1 -80,0 a45,45 0 0,1 90,0" 
                      fill="none" 
                      stroke="#6B46C1" 
                      stroke-width="3"
                      opacity="0"
                      class="spiralengine-spiral-path"/>
                
                <!-- Animated Particles -->
                <g class="spiralengine-particles">
                    <circle class="particle particle-1" r="3" fill="#9333EA" opacity="0">
                        <animateMotion dur="8s" repeatCount="indefinite" begin="3s">
                            <mpath href="#spiral-path"/>
                        </animateMotion>
                    </circle>
                    <circle class="particle particle-2" r="2.5" fill="#7C3AED" opacity="0">
                        <animateMotion dur="7s" repeatCount="indefinite" begin="3.5s">
                            <mpath href="#spiral-path"/>
                        </animateMotion>
                    </circle>
                    <circle class="particle particle-3" r="2" fill="#6B46C1" opacity="0">
                        <animateMotion dur="6s" repeatCount="indefinite" begin="4s">
                            <mpath href="#spiral-path"/>
                        </animateMotion>
                    </circle>
                    <circle class="particle particle-4" r="2.5" fill="#9333EA" opacity="0">
                        <animateMotion dur="9s" repeatCount="indefinite" begin="4.5s">
                            <mpath href="#spiral-path"/>
                        </animateMotion>
                    </circle>
                    <circle class="particle particle-5" r="3" fill="#7C3AED" opacity="0">
                        <animateMotion dur="10s" repeatCount="indefinite" begin="5s">
                            <mpath href="#spiral-path"/>
                        </animateMotion>
                    </circle>
                </g>
            </svg>
            
            <!-- Confetti Effect -->
            <div class="spiralengine-confetti-container"></div>
        </div>
        
        <!-- Progress Messages -->
        <div class="spiralengine-progress-messages">
            <h2 class="spiralengine-progress-title">
                <span class="spiralengine-message-1" style="display: none;">
                    <?php _e('Mapping Your Assessment', 'spiralengine'); ?>
                </span>
                <span class="spiralengine-message-2" style="display: none;">
                    <?php _e('Building Your Personal Dashboard', 'spiralengine'); ?>
                </span>
                <span class="spiralengine-message-3" style="display: none;">
                    <?php _e('Your Journey Begins Now!', 'spiralengine'); ?>
                </span>
            </h2>
            
            <div class="spiralengine-progress-dots">
                <span class="dot dot-1"></span>
                <span class="dot dot-2"></span>
                <span class="dot dot-3"></span>
            </div>
        </div>
        
        <!-- Redirect Notice -->
        <div class="spiralengine-redirect-notice" style="display: none;">
            <p><?php _e('Redirecting to your dashboard in <span class="countdown">3</span> seconds...', 'spiralengine'); ?></p>
            <a href="#" class="spiralengine-skip-link"><?php _e('Go now →', 'spiralengine'); ?></a>
        </div>
    </div>
</div>

<style>
/* Inline critical animation styles for immediate render */
.spiralengine-success-animation {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.98);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.spiralengine-animation-container {
    text-align: center;
    max-width: 500px;
    padding: 2rem;
}

.spiralengine-spiral-wrapper {
    position: relative;
    margin: 0 auto 2rem;
    width: 200px;
    height: 200px;
}

@keyframes spiralGrow {
    0% {
        transform: scale(0.1) rotate(0deg);
        opacity: 0;
    }
    50% {
        opacity: 1;
    }
    100% {
        transform: scale(1) rotate(360deg);
        opacity: 1;
    }
}

@keyframes spiralRotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@keyframes particleFade {
    0% {
        opacity: 0;
    }
    50% {
        opacity: 1;
    }
    100% {
        opacity: 0.6;
    }
}

@keyframes confettiFall {
    0% {
        transform: translateY(-100vh) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
    }
}

.spiralengine-spiral-path {
    animation: spiralGrow 3s ease-out forwards;
}

.spiralengine-spiral-logo.rotating {
    animation: spiralRotate 20s linear infinite;
}

.particle {
    animation: particleFade 2s ease-out forwards;
}

.spiralengine-confetti {
    position: absolute;
    width: 10px;
    height: 10px;
    background: #9333EA;
    animation: confettiFall 3s linear forwards;
}

.spiralengine-progress-messages {
    margin-bottom: 2rem;
}

.spiralengine-progress-title {
    font-size: 1.75rem;
    font-weight: 600;
    color: #1F2937;
    margin-bottom: 1rem;
    height: 2.5rem;
}

.spiralengine-progress-dots {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.spiralengine-progress-dots .dot {
    width: 8px;
    height: 8px;
    background: #E5E7EB;
    border-radius: 50%;
    transition: background 0.3s;
}

.spiralengine-progress-dots .dot.active {
    background: #6B46C1;
}

.spiralengine-redirect-notice {
    font-size: 0.875rem;
    color: #6B7280;
}

.spiralengine-skip-link {
    color: #6B46C1;
    text-decoration: none;
    font-weight: 500;
}

.spiralengine-skip-link:hover {
    text-decoration: underline;
}
</style>

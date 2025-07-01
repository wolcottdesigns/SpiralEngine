<?php
/**
 * Setup Wizard Class
 * File: includes/class-setup-wizard.php
 * 
 * Guides users through initial plugin setup
 * 
 * @package SpiralEngine
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Setup Wizard Class
 */
class SpiralEngine_Setup_Wizard {
    
    /**
     * Current step
     * 
     * @var string
     */
    private $step = '';
    
    /**
     * Steps
     * 
     * @var array
     */
    private $steps = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menus' ) );
        add_action( 'admin_init', array( $this, 'setup_wizard' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }
    
    /**
     * Add admin menus/screens
     */
    public function admin_menus() {
        add_dashboard_page( '', '', 'manage_options', 'spiralengine-setup', '' );
    }
    
    /**
     * Setup wizard
     */
    public function setup_wizard() {
        if ( empty( $_GET['page'] ) || 'spiralengine-setup' !== $_GET['page'] ) {
            return;
        }
        
        $this->steps = array(
            'welcome' => array(
                'name' => __( 'Welcome', 'spiralengine' ),
                'view' => array( $this, 'setup_welcome' ),
                'handler' => ''
            ),
            'profile' => array(
                'name' => __( 'Profile', 'spiralengine' ),
                'view' => array( $this, 'setup_profile' ),
                'handler' => array( $this, 'setup_profile_save' )
            ),
            'features' => array(
                'name' => __( 'Features', 'spiralengine' ),
                'view' => array( $this, 'setup_features' ),
                'handler' => array( $this, 'setup_features_save' )
            ),
            'membership' => array(
                'name' => __( 'Membership', 'spiralengine' ),
                'view' => array( $this, 'setup_membership' ),
                'handler' => array( $this, 'setup_membership_save' )
            ),
            'ready' => array(
                'name' => __( 'Ready!', 'spiralengine' ),
                'view' => array( $this, 'setup_ready' ),
                'handler' => ''
            )
        );
        
        $this->step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : current( array_keys( $this->steps ) );
        
        // Save data if submitted
        if ( ! empty( $_POST['save_step'] ) && isset( $this->steps[ $this->step ]['handler'] ) ) {
            call_user_func( $this->steps[ $this->step ]['handler'] );
        }
        
        ob_start();
        $this->setup_wizard_header();
        $this->setup_wizard_steps();
        $this->setup_wizard_content();
        $this->setup_wizard_footer();
        exit;
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if ( empty( $_GET['page'] ) || 'spiralengine-setup' !== $_GET['page'] ) {
            return;
        }
        
        wp_enqueue_style( 'spiralengine-setup', SPIRALENGINE_PLUGIN_URL . 'assets/css/setup-wizard.css', array( 'dashicons' ), SPIRALENGINE_VERSION );
        wp_enqueue_script( 'spiralengine-setup', SPIRALENGINE_PLUGIN_URL . 'assets/js/setup-wizard.js', array( 'jquery' ), SPIRALENGINE_VERSION );
        
        wp_localize_script( 'spiralengine-setup', 'spiralengine_setup', array(
            'nonce' => wp_create_nonce( 'spiralengine-setup' ),
            'ajax_url' => admin_url( 'admin-ajax.php' )
        ) );
    }
    
    /**
     * Setup wizard header
     */
    public function setup_wizard_header() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta name="viewport" content="width=device-width" />
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title><?php esc_html_e( 'SpiralEngine &rsaquo; Setup Wizard', 'spiralengine' ); ?></title>
            <?php do_action( 'admin_enqueue_scripts' ); ?>
            <?php do_action( 'admin_print_styles' ); ?>
            <?php do_action( 'admin_head' ); ?>
        </head>
        <body class="spiralengine-setup wp-core-ui">
            <h1 class="spiralengine-logo">
                <img src="<?php echo esc_url( SPIRALENGINE_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="SpiralEngine" />
            </h1>
        <?php
    }
    
    /**
     * Setup wizard footer
     */
    public function setup_wizard_footer() {
        ?>
            <?php do_action( 'admin_print_footer_scripts' ); ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Setup wizard steps
     */
    public function setup_wizard_steps() {
        $output_steps = $this->steps;
        ?>
        <ol class="spiralengine-setup-steps">
            <?php foreach ( $output_steps as $step_key => $step ) : ?>
                <li class="<?php
                    if ( $step_key === $this->step ) {
                        echo 'active';
                    } elseif ( array_search( $this->step, array_keys( $this->steps ) ) > array_search( $step_key, array_keys( $this->steps ) ) ) {
                        echo 'done';
                    }
                ?>">
                    <?php echo esc_html( $step['name'] ); ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php
    }
    
    /**
     * Setup wizard content
     */
    public function setup_wizard_content() {
        echo '<div class="spiralengine-setup-content">';
        call_user_func( $this->steps[ $this->step ]['view'] );
        echo '</div>';
    }
    
    /**
     * Get next step link
     * 
     * @return string
     */
    public function get_next_step_link() {
        $keys = array_keys( $this->steps );
        $step_index = array_search( $this->step, $keys );
        
        if ( isset( $keys[ $step_index + 1 ] ) ) {
            return add_query_arg( 'step', $keys[ $step_index + 1 ] );
        }
        
        return admin_url();
    }
    
    /**
     * Welcome step
     */
    public function setup_welcome() {
        ?>
        <h1><?php esc_html_e( 'Welcome to SpiralEngine!', 'spiralengine' ); ?></h1>
        <p><?php esc_html_e( 'Thank you for choosing SpiralEngine for your mental health tracking needs. This quick setup wizard will help you configure the basic settings.', 'spiralengine' ); ?></p>
        
        <div class="spiralengine-setup-features">
            <div class="feature">
                <span class="dashicons dashicons-chart-line"></span>
                <h3><?php esc_html_e( 'Track Your Journey', 'spiralengine' ); ?></h3>
                <p><?php esc_html_e( 'Monitor overthinking patterns, mood changes, and anxiety levels with our intuitive tracking tools.', 'spiralengine' ); ?></p>
            </div>
            
            <div class="feature">
                <span class="dashicons dashicons-analytics"></span>
                <h3><?php esc_html_e( 'Gain Insights', 'spiralengine' ); ?></h3>
                <p><?php esc_html_e( 'Discover patterns and triggers with powerful analytics and AI-powered insights.', 'spiralengine' ); ?></p>
            </div>
            
            <div class="feature">
                <span class="dashicons dashicons-awards"></span>
                <h3><?php esc_html_e( 'Achieve Goals', 'spiralengine' ); ?></h3>
                <p><?php esc_html_e( 'Set and track mental health goals with milestone tracking and progress monitoring.', 'spiralengine' ); ?></p>
            </div>
        </div>
        
        <p class="spiralengine-setup-actions step">
            <a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button-primary button button-large">
                <?php esc_html_e( 'Let\'s Get Started', 'spiralengine' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url() ); ?>" class="button button-large">
                <?php esc_html_e( 'Skip Setup', 'spiralengine' ); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * Profile step
     */
    public function setup_profile() {
        $current_user = wp_get_current_user();
        ?>
        <h1><?php esc_html_e( 'Your Profile', 'spiralengine' ); ?></h1>
        <form method="post">
            
            <p><?php esc_html_e( 'Let\'s personalize your experience. This information helps us provide better insights and recommendations.', 'spiralengine' ); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="display_name"><?php esc_html_e( 'Display Name', 'spiralengine' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="display_name" name="display_name" class="regular-text" value="<?php echo esc_attr( $current_user->display_name ); ?>" />
                        <p class="description"><?php esc_html_e( 'How would you like to be addressed?', 'spiralengine' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="timezone"><?php esc_html_e( 'Timezone', 'spiralengine' ); ?></label>
                    </th>
                    <td>
                        <?php
                        $selected_timezone = get_user_meta( $current_user->ID, 'spiralengine_timezone', true );
                        if ( ! $selected_timezone ) {
                            $selected_timezone = get_option( 'timezone_string' );
                        }
                        ?>
                        <select id="timezone" name="timezone" class="regular-text">
                            <?php echo wp_timezone_choice( $selected_timezone ); ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Your local timezone for accurate tracking.', 'spiralengine' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notification_time"><?php esc_html_e( 'Daily Reminder', 'spiralengine' ); ?></label>
                    </th>
                    <td>
                        <input type="time" id="notification_time" name="notification_time" value="09:00" />
                        <p class="description"><?php esc_html_e( 'When would you like to receive daily check-in reminders?', 'spiralengine' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Privacy', 'spiralengine' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="anonymous_analytics" value="1" checked />
                            <?php esc_html_e( 'Share anonymous usage data to help improve SpiralEngine', 'spiralengine' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Your personal data and episode content are never shared.', 'spiralengine' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="spiralengine-setup-actions step">
                <input type="submit" class="button-primary button button-large" value="<?php esc_attr_e( 'Continue', 'spiralengine' ); ?>" name="save_step" />
                <a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-large">
                    <?php esc_html_e( 'Skip This Step', 'spiralengine' ); ?>
                </a>
                <?php wp_nonce_field( 'spiralengine-setup' ); ?>
            </p>
        </form>
        <?php
    }
    
    /**
     * Save profile step
     */
    public function setup_profile_save() {
        check_admin_referer( 'spiralengine-setup' );
        
        $current_user_id = get_current_user_id();
        
        if ( isset( $_POST['display_name'] ) ) {
            wp_update_user( array(
                'ID' => $current_user_id,
                'display_name' => sanitize_text_field( $_POST['display_name'] )
            ) );
        }
        
        if ( isset( $_POST['timezone'] ) ) {
            update_user_meta( $current_user_id, 'spiralengine_timezone', sanitize_text_field( $_POST['timezone'] ) );
        }
        
        if ( isset( $_POST['notification_time'] ) ) {
            update_user_meta( $current_user_id, 'spiralengine_reminder_time', sanitize_text_field( $_POST['notification_time'] ) );
        }
        
        update_user_meta( $current_user_id, 'spiralengine_anonymous_analytics', ! empty( $_POST['anonymous_analytics'] ) );
        
        wp_redirect( esc_url_raw( $this->get_next_step_link() ) );
        exit;
    }
    
    /**
     * Features step
     */
    public function setup_features() {
        ?>
        <h1><?php esc_html_e( 'Choose Your Features', 'spiralengine' ); ?></h1>
        <form method="post">
            
            <p><?php esc_html_e( 'Select the tracking widgets you\'d like to use. You can always change these later.', 'spiralengine' ); ?></p>
            
            <div class="spiralengine-widget-selection">
                <?php
                $widgets = SpiralEngine_Widget_Loader::get_widgets();
                $user_widgets = get_user_meta( get_current_user_id(), 'spiralengine_enabled_widgets', true );
                if ( ! is_array( $user_widgets ) ) {
                    $user_widgets = array( 'overthinking', 'mood', 'anxiety' ); // Default widgets
                }
                
                foreach ( $widgets as $widget_id => $widget ) :
                    $config = $widget->get_config();
                    ?>
                    <div class="widget-option">
                        <label>
                            <input type="checkbox" name="widgets[]" value="<?php echo esc_attr( $widget_id ); ?>" 
                                <?php checked( in_array( $widget_id, $user_widgets ) ); ?> />
                            <div class="widget-info">
                                <h3><?php echo esc_html( $config['name'] ); ?></h3>
                                <p><?php echo esc_html( $config['description'] ); ?></p>
                                <?php if ( ! in_array( 'free', $config['tiers'] ) ) : ?>
                                    <span class="tier-badge"><?php echo esc_html( ucfirst( $config['tiers'][0] ) ); ?>+</span>
                                <?php endif; ?>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <h2><?php esc_html_e( 'Additional Settings', 'spiralengine' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="default_severity"><?php esc_html_e( 'Default Severity', 'spiralengine' ); ?></label>
                    </th>
                    <td>
                        <input type="range" id="default_severity" name="default_severity" min="1" max="10" value="5" />
                        <span class="severity-value">5</span>
                        <p class="description"><?php esc_html_e( 'Default severity level for new episodes.', 'spiralengine' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Quick Entry', 'spiralengine' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="quick_entry" value="1" checked />
                            <?php esc_html_e( 'Enable quick entry mode for faster episode tracking', 'spiralengine' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="spiralengine-setup-actions step">
                <input type="submit" class="button-primary button button-large" value="<?php esc_attr_e( 'Continue', 'spiralengine' ); ?>" name="save_step" />
                <a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-large">
                    <?php esc_html_e( 'Skip This Step', 'spiralengine' ); ?>
                </a>
                <?php wp_nonce_field( 'spiralengine-setup' ); ?>
            </p>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('#default_severity').on('input', function() {
                $('.severity-value').text($(this).val());
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save features step
     */
    public function setup_features_save() {
        check_admin_referer( 'spiralengine-setup' );
        
        $current_user_id = get_current_user_id();
        
        if ( isset( $_POST['widgets'] ) && is_array( $_POST['widgets'] ) ) {
            $enabled_widgets = array_map( 'sanitize_text_field', $_POST['widgets'] );
            update_user_meta( $current_user_id, 'spiralengine_enabled_widgets', $enabled_widgets );
        }
        
        if ( isset( $_POST['default_severity'] ) ) {
            update_user_meta( $current_user_id, 'spiralengine_default_severity', intval( $_POST['default_severity'] ) );
        }
        
        update_user_meta( $current_user_id, 'spiralengine_quick_entry', ! empty( $_POST['quick_entry'] ) );
        
        wp_redirect( esc_url_raw( $this->get_next_step_link() ) );
        exit;
    }
    
    /**
     * Membership step
     */
    public function setup_membership() {
        ?>
        <h1><?php esc_html_e( 'Choose Your Plan', 'spiralengine' ); ?></h1>
        <form method="post">
            
            <p><?php esc_html_e( 'Select the plan that best fits your needs. You can upgrade or change your plan at any time.', 'spiralengine' ); ?></p>
            
            <div class="spiralengine-pricing-table">
                <?php
                $tiers = array(
                    'free' => array(
                        'name' => __( 'Free', 'spiralengine' ),
                        'price' => __( 'Free', 'spiralengine' ),
                        'features' => array(
                            __( '3 episodes per day', 'spiralengine' ),
                            __( 'Overthinking tracker', 'spiralengine' ),
                            __( 'Basic analytics', 'spiralengine' ),
                            __( 'CSV export', 'spiralengine' )
                        )
                    ),
                    'silver' => array(
                        'name' => __( 'Silver', 'spiralengine' ),
                        'price' => __( '$19.99/month', 'spiralengine' ),
                        'features' => array(
                            __( '25 episodes per day', 'spiralengine' ),
                            __( 'All basic widgets', 'spiralengine' ),
                            __( 'AI-powered insights', 'spiralengine' ),
                            __( 'Goals & milestones', 'spiralengine' ),
                            __( 'PDF export', 'spiralengine' ),
                            __( 'Email support', 'spiralengine' )
                        ),
                        'popular' => true
                    ),
                    'gold' => array(
                        'name' => __( 'Gold', 'spiralengine' ),
                        'price' => __( '$39.99/month', 'spiralengine' ),
                        'features' => array(
                            __( '50 episodes per day', 'spiralengine' ),
                            __( 'All widgets', 'spiralengine' ),
                            __( 'Advanced AI analysis', 'spiralengine' ),
                            __( 'API access', 'spiralengine' ),
                            __( 'Priority support', 'spiralengine' ),
                            __( 'Custom reports', 'spiralengine' )
                        )
                    )
                );
                
                foreach ( $tiers as $tier_id => $tier ) :
                    ?>
                    <div class="pricing-column <?php echo ! empty( $tier['popular'] ) ? 'popular' : ''; ?>">
                        <?php if ( ! empty( $tier['popular'] ) ) : ?>
                            <div class="popular-badge"><?php esc_html_e( 'Most Popular', 'spiralengine' ); ?></div>
                        <?php endif; ?>
                        
                        <h3><?php echo esc_html( $tier['name'] ); ?></h3>
                        <div class="price"><?php echo esc_html( $tier['price'] ); ?></div>
                        
                        <ul class="features">
                            <?php foreach ( $tier['features'] as $feature ) : ?>
                                <li><span class="dashicons dashicons-yes"></span> <?php echo esc_html( $feature ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <label>
                            <input type="radio" name="tier" value="<?php echo esc_attr( $tier_id ); ?>" 
                                <?php checked( $tier_id, 'free' ); ?> />
                            <span class="button button-large <?php echo ! empty( $tier['popular'] ) ? 'button-primary' : ''; ?>">
                                <?php
                                if ( $tier_id === 'free' ) {
                                    esc_html_e( 'Start Free', 'spiralengine' );
                                } else {
                                    esc_html_e( 'Choose Plan', 'spiralengine' );
                                }
                                ?>
                            </span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <p class="spiralengine-setup-actions step">
                <input type="submit" class="button-primary button button-large" value="<?php esc_attr_e( 'Continue', 'spiralengine' ); ?>" name="save_step" />
                <?php wp_nonce_field( 'spiralengine-setup' ); ?>
            </p>
        </form>
        <?php
    }
    
    /**
     * Save membership step
     */
    public function setup_membership_save() {
        check_admin_referer( 'spiralengine-setup' );
        
        if ( isset( $_POST['tier'] ) ) {
            $tier = sanitize_text_field( $_POST['tier'] );
            $membership = new SpiralEngine_Membership( get_current_user_id() );
            
            if ( $tier === 'free' ) {
                $membership->set_tier( 'free' );
            } else {
                // Store selected tier for later payment processing
                update_user_meta( get_current_user_id(), 'spiralengine_selected_tier', $tier );
                // In real implementation, would redirect to payment
            }
        }
        
        // Mark setup as complete
        update_option( 'spiralengine_setup_complete', true );
        update_user_meta( get_current_user_id(), 'spiralengine_setup_complete', true );
        
        wp_redirect( esc_url_raw( $this->get_next_step_link() ) );
        exit;
    }
    
    /**
     * Ready step
     */
    public function setup_ready() {
        ?>
        <h1><?php esc_html_e( 'You\'re All Set!', 'spiralengine' ); ?></h1>
        
        <div class="spiralengine-setup-complete">
            <span class="dashicons dashicons-yes-alt"></span>
        </div>
        
        <p class="lead"><?php esc_html_e( 'Congratulations! SpiralEngine is ready to help you on your mental health journey.', 'spiralengine' ); ?></p>
        
        <h2><?php esc_html_e( 'What\'s Next?', 'spiralengine' ); ?></h2>
        
        <div class="next-steps">
            <div class="step">
                <h3><?php esc_html_e( '1. Track Your First Episode', 'spiralengine' ); ?></h3>
                <p><?php esc_html_e( 'Start by recording your first overthinking episode or mood check-in.', 'spiralengine' ); ?></p>
            </div>
            
            <div class="step">
                <h3><?php esc_html_e( '2. Explore the Dashboard', 'spiralengine' ); ?></h3>
                <p><?php esc_html_e( 'Get familiar with your personal dashboard and available widgets.', 'spiralengine' ); ?></p>
            </div>
            
            <div class="step">
                <h3><?php esc_html_e( '3. Set Your First Goal', 'spiralengine' ); ?></h3>
                <p><?php esc_html_e( 'Create a mental health goal and track your progress over time.', 'spiralengine' ); ?></p>
            </div>
        </div>
        
        <h2><?php esc_html_e( 'Helpful Resources', 'spiralengine' ); ?></h2>
        
        <ul class="resources">
            <li><a href="#" target="_blank"><?php esc_html_e( 'Quick Start Guide', 'spiralengine' ); ?></a></li>
            <li><a href="#" target="_blank"><?php esc_html_e( 'Video Tutorials', 'spiralengine' ); ?></a></li>
            <li><a href="#" target="_blank"><?php esc_html_e( 'Community Forum', 'spiralengine' ); ?></a></li>
            <li><a href="#" target="_blank"><?php esc_html_e( 'Support Center', 'spiralengine' ); ?></a></li>
        </ul>
        
        <p class="spiralengine-setup-actions step">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=spiralengine' ) ); ?>" class="button button-primary button-large">
                <?php esc_html_e( 'Go to Dashboard', 'spiralengine' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=spiralengine-track' ) ); ?>" class="button button-large">
                <?php esc_html_e( 'Track First Episode', 'spiralengine' ); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * Should show setup wizard
     * 
     * @return bool
     */
    public static function should_show_wizard() {
        // Don't show if already completed
        if ( get_option( 'spiralengine_setup_complete' ) ) {
            return false;
        }
        
        // Don't show if user already completed
        if ( get_user_meta( get_current_user_id(), 'spiralengine_setup_complete', true ) ) {
            return false;
        }
        
        // Only show to admins
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        
        return true;
    }
}

// Initialize
new SpiralEngine_Setup_Wizard();

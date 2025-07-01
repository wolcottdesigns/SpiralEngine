<?php
/**
 * Demo Content Generator Class
 * File: includes/class-demo-content.php
 * 
 * Generates demo content for testing and demonstration purposes
 * 
 * @package SpiralEngine
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Demo Content Generator Class
 */
class SpiralEngine_Demo_Content {
    
    /**
     * Demo user IDs
     * 
     * @var array
     */
    private $demo_users = array();
    
    /**
     * Demo data
     * 
     * @var array
     */
    private $demo_data = array(
        'thoughts' => array(
            'I keep wondering if I made the right decision about changing jobs.',
            'What if my presentation tomorrow doesn\'t go well?',
            'I can\'t stop thinking about that awkward conversation from last week.',
            'Did I remember to lock the door? I should go check.',
            'What if I\'m not good enough for this promotion?',
            'I keep replaying that mistake I made at work.',
            'Everyone probably thinks I\'m incompetent after that meeting.',
            'What if I fail the exam even though I studied hard?',
            'I wonder if my friends are talking about me behind my back.',
            'Did I say something wrong? They seemed upset.',
            'What if I never achieve my goals?',
            'I can\'t stop worrying about my health.',
            'Maybe I should have handled that situation differently.',
            'What if I\'m making the wrong choices in life?',
            'I keep thinking about all the things that could go wrong.'
        ),
        'contexts' => array(
            'At work, preparing for tomorrow',
            'At home, trying to relax',
            'Before going to sleep',
            'During my commute',
            'After a meeting',
            'While working on a project',
            'During lunch break',
            'After a phone call',
            'While exercising',
            'In a social gathering',
            'Studying for exams',
            'Weekend at home',
            'After watching the news',
            'While making decisions',
            'During quiet moments'
        ),
        'moods' => array(
            'anxious', 'worried', 'stressed', 'overwhelmed', 'frustrated',
            'confused', 'tired', 'restless', 'nervous', 'uncertain',
            'hopeful', 'calm', 'content', 'motivated', 'peaceful'
        ),
        'coping_strategies' => array(
            'deep breathing', 'meditation', 'went for a walk', 'called a friend',
            'journaling', 'listened to music', 'exercise', 'mindfulness',
            'distraction', 'positive self-talk', 'grounding techniques',
            'progressive relaxation', 'took a break', 'practiced gratitude'
        ),
        'triggers' => array(
            'Work deadline', 'Social situation', 'Health concern', 'Financial worry',
            'Relationship issue', 'Past mistake', 'Future uncertainty', 'Performance anxiety',
            'Decision making', 'Conflict', 'Change', 'Criticism', 'Responsibility',
            'Perfectionism', 'Comparison'
        )
    );
    
    /**
     * Generate demo content
     * 
     * @param array $options
     * @return array
     */
    public function generate( $options = array() ) {
        $defaults = array(
            'users' => 3,
            'days' => 30,
            'episodes_per_day' => array( 1, 4 ),
            'include_patterns' => true,
            'include_goals' => true,
            'include_ai_analysis' => true
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        // Create demo users
        $this->create_demo_users( $options['users'] );
        
        // Generate episodes for each user
        foreach ( $this->demo_users as $user_id => $user_data ) {
            $this->generate_user_episodes( $user_id, $user_data, $options );
            
            if ( $options['include_goals'] && $user_data['tier'] !== 'free' ) {
                $this->generate_user_goals( $user_id, $user_data );
            }
        }
        
        // Generate some AI analyses if enabled
        if ( $options['include_ai_analysis'] ) {
            $this->generate_ai_analyses();
        }
        
        return array(
            'users' => $this->demo_users,
            'episodes_created' => $this->count_episodes(),
            'goals_created' => $this->count_goals()
        );
    }
    
    /**
     * Create demo users
     * 
     * @param int $count
     */
    private function create_demo_users( $count ) {
        $personas = array(
            array(
                'name' => 'Sarah Johnson',
                'email' => 'sarah.demo@spiralengine.test',
                'tier' => 'silver',
                'pattern' => 'improving',
                'primary_widget' => 'overthinking',
                'story' => 'Working professional dealing with work-related stress'
            ),
            array(
                'name' => 'Michael Chen',
                'email' => 'michael.demo@spiralengine.test',
                'tier' => 'gold',
                'pattern' => 'cyclic',
                'primary_widget' => 'anxiety',
                'story' => 'Graduate student managing academic pressure'
            ),
            array(
                'name' => 'Emily Rodriguez',
                'email' => 'emily.demo@spiralengine.test',
                'tier' => 'free',
                'pattern' => 'stable',
                'primary_widget' => 'mood',
                'story' => 'New user exploring mental health tracking'
            ),
            array(
                'name' => 'David Thompson',
                'email' => 'david.demo@spiralengine.test',
                'tier' => 'platinum',
                'pattern' => 'variable',
                'primary_widget' => 'daily-checkin',
                'story' => 'Long-term user with comprehensive tracking'
            ),
            array(
                'name' => 'Lisa Park',
                'email' => 'lisa.demo@spiralengine.test',
                'tier' => 'bronze',
                'pattern' => 'improving',
                'primary_widget' => 'sleep',
                'story' => 'Focusing on sleep quality and its impact'
            )
        );
        
        for ( $i = 0; $i < min( $count, count( $personas ) ); $i++ ) {
            $persona = $personas[ $i ];
            
            // Create user
            $user_id = wp_create_user(
                'demo_' . sanitize_user( strtolower( str_replace( ' ', '_', $persona['name'] ) ) ),
                wp_generate_password(),
                $persona['email']
            );
            
            if ( ! is_wp_error( $user_id ) ) {
                // Update user info
                wp_update_user( array(
                    'ID' => $user_id,
                    'display_name' => $persona['name'],
                    'first_name' => explode( ' ', $persona['name'] )[0],
                    'last_name' => explode( ' ', $persona['name'] )[1]
                ) );
                
                // Set membership tier
                $membership = new SpiralEngine_Membership( $user_id );
                $membership->set_tier( $persona['tier'] );
                
                // Store demo user data
                $this->demo_users[ $user_id ] = $persona;
                
                // Mark as demo user
                update_user_meta( $user_id, 'spiralengine_demo_user', true );
                update_user_meta( $user_id, 'spiralengine_demo_story', $persona['story'] );
            }
        }
    }
    
    /**
     * Generate episodes for user
     * 
     * @param int $user_id
     * @param array $user_data
     * @param array $options
     */
    private function generate_user_episodes( $user_id, $user_data, $options ) {
        global $wpdb;
        
        $pattern = $user_data['pattern'];
        $primary_widget = $user_data['primary_widget'];
        
        // Get available widgets for user's tier
        $available_widgets = $this->get_tier_widgets( $user_data['tier'] );
        
        for ( $day = $options['days']; $day >= 0; $day-- ) {
            $date = date( 'Y-m-d', strtotime( "-{$day} days" ) );
            $episodes_today = rand( $options['episodes_per_day'][0], $options['episodes_per_day'][1] );
            
            // Apply pattern to episode count and severity
            $severity_modifier = $this->get_pattern_modifier( $pattern, $day, $options['days'] );
            
            for ( $e = 0; $e < $episodes_today; $e++ ) {
                // 60% chance of primary widget, 40% other widgets
                if ( rand( 1, 100 ) <= 60 ) {
                    $widget_id = $primary_widget;
                } else {
                    $widget_id = $available_widgets[ array_rand( $available_widgets ) ];
                }
                
                // Generate episode data
                $episode_data = $this->generate_episode_data( $widget_id, $severity_modifier );
                
                // Set timestamp
                $hour = 6 + ( $e * 4 ) + rand( 0, 3 ); // Spread throughout day
                $timestamp = $date . ' ' . sprintf( '%02d:%02d:00', min( $hour, 23 ), rand( 0, 59 ) );
                
                // Insert episode
                $wpdb->insert(
                    $wpdb->prefix . 'spiralengine_episodes',
                    array(
                        'user_id' => $user_id,
                        'widget_id' => $widget_id,
                        'severity' => $episode_data['severity'],
                        'data' => json_encode( $episode_data['data'] ),
                        'metadata' => json_encode( array(
                            'demo' => true,
                            'pattern' => $pattern,
                            'day_number' => $options['days'] - $day
                        ) ),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp
                    ),
                    array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
                );
            }
        }
    }
    
    /**
     * Get pattern modifier
     * 
     * @param string $pattern
     * @param int $day
     * @param int $total_days
     * @return float
     */
    private function get_pattern_modifier( $pattern, $day, $total_days ) {
        $progress = ( $total_days - $day ) / $total_days;
        
        switch ( $pattern ) {
            case 'improving':
                // Severity decreases over time
                return 1 - ( $progress * 0.5 );
                
            case 'worsening':
                // Severity increases over time
                return 0.5 + ( $progress * 0.5 );
                
            case 'cyclic':
                // Weekly cycles
                $week_progress = ( ( $total_days - $day ) % 7 ) / 7;
                return 0.5 + ( sin( $week_progress * 2 * M_PI ) * 0.3 );
                
            case 'stable':
                // Consistent with small variations
                return 0.7 + ( rand( -20, 20 ) / 100 );
                
            case 'variable':
                // High variability
                return 0.3 + ( rand( 0, 70 ) / 100 );
                
            default:
                return 1;
        }
    }
    
    /**
     * Generate episode data
     * 
     * @param string $widget_id
     * @param float $severity_modifier
     * @return array
     */
    private function generate_episode_data( $widget_id, $severity_modifier ) {
        $base_severity = rand( 3, 8 );
        $severity = max( 1, min( 10, round( $base_severity * $severity_modifier ) ) );
        
        switch ( $widget_id ) {
            case 'overthinking':
                $data = array(
                    'thought' => $this->demo_data['thoughts'][ array_rand( $this->demo_data['thoughts'] ) ],
                    'context' => $this->demo_data['contexts'][ array_rand( $this->demo_data['contexts'] ) ],
                    'feeling' => $this->demo_data['moods'][ array_rand( $this->demo_data['moods'] ) ],
                    'duration' => rand( 5, 60 ),
                    'coping_attempted' => rand( 0, 1 ) ? $this->demo_data['coping_strategies'][ array_rand( $this->demo_data['coping_strategies'] ) ] : '',
                    'helped' => rand( 0, 1 ) ? 'yes' : 'no'
                );
                break;
                
            case 'mood':
                $data = array(
                    'mood_rating' => $severity,
                    'energy_level' => rand( 1, 10 ),
                    'mood_words' => array_slice( $this->demo_data['moods'], 0, rand( 2, 4 ) ),
                    'activities' => $this->generate_random_activities(),
                    'social_interaction' => rand( 0, 10 ),
                    'notes' => rand( 0, 1 ) ? 'Today was ' . $this->demo_data['moods'][ array_rand( $this->demo_data['moods'] ) ] : ''
                );
                $severity = 11 - $data['mood_rating']; // Invert for mood
                break;
                
            case 'anxiety':
                $data = array(
                    'anxiety_level' => $severity,
                    'trigger' => $this->demo_data['triggers'][ array_rand( $this->demo_data['triggers'] ) ],
                    'physical_symptoms' => $this->generate_random_symptoms(),
                    'duration' => rand( 10, 120 ),
                    'coping_used' => array_slice( $this->demo_data['coping_strategies'], 0, rand( 1, 3 ) ),
                    'effectiveness' => rand( 1, 10 )
                );
                break;
                
            case 'sleep':
                $bedtime_hour = rand( 21, 24 );
                $wake_hour = rand( 5, 9 );
                $data = array(
                    'bedtime' => sprintf( '%02d:%02d', $bedtime_hour % 24, rand( 0, 59 ) ),
                    'wake_time' => sprintf( '%02d:%02d', $wake_hour, rand( 0, 59 ) ),
                    'quality' => 11 - $severity, // Invert for sleep quality
                    'interruptions' => rand( 0, 5 ),
                    'dreams' => rand( 0, 1 ) ? 'Had vivid dreams' : '',
                    'factors' => array(
                        'caffeine' => rand( 0, 1 ),
                        'exercise' => rand( 0, 1 ),
                        'stress' => $severity > 5 ? 1 : 0
                    )
                );
                break;
                
            case 'daily-checkin':
                $data = array(
                    'overall_feeling' => 11 - $severity,
                    'energy_level' => rand( 1, 10 ),
                    'accomplishments' => $this->generate_accomplishments(),
                    'challenges' => $this->generate_challenges(),
                    'gratitude' => $this->generate_gratitude(),
                    'self_care' => array(
                        'exercise' => rand( 0, 1 ),
                        'meditation' => rand( 0, 1 ),
                        'healthy_eating' => rand( 0, 1 ),
                        'social_connection' => rand( 0, 1 )
                    )
                );
                break;
                
            case 'trigger':
                $data = array(
                    'trigger' => $this->demo_data['triggers'][ array_rand( $this->demo_data['triggers'] ) ],
                    'intensity' => $severity,
                    'emotions' => array_slice( $this->demo_data['moods'], 0, rand( 2, 4 ) ),
                    'physical_response' => $this->generate_random_symptoms(),
                    'coping_used' => $this->demo_data['coping_strategies'][ array_rand( $this->demo_data['coping_strategies'] ) ],
                    'effectiveness' => rand( 1, 10 ),
                    'notes' => 'Noticed this trigger in ' . $this->demo_data['contexts'][ array_rand( $this->demo_data['contexts'] ) ]
                );
                break;
                
            default:
                $data = array(
                    'value' => $severity,
                    'notes' => 'Demo data for ' . $widget_id
                );
        }
        
        return array(
            'severity' => $severity,
            'data' => $data
        );
    }
    
    /**
     * Get widgets available for tier
     * 
     * @param string $tier
     * @return array
     */
    private function get_tier_widgets( $tier ) {
        $tier_widgets = array(
            'free' => array( 'overthinking' ),
            'bronze' => array( 'overthinking', 'mood' ),
            'silver' => array( 'overthinking', 'mood', 'anxiety', 'sleep' ),
            'gold' => array( 'overthinking', 'mood', 'anxiety', 'sleep', 'daily-checkin', 'trigger' ),
            'platinum' => array( 'overthinking', 'mood', 'anxiety', 'sleep', 'daily-checkin', 'trigger' )
        );
        
        return $tier_widgets[ $tier ] ?? array( 'overthinking' );
    }
    
    /**
     * Generate random symptoms
     * 
     * @return array
     */
    private function generate_random_symptoms() {
        $symptoms = array(
            'racing_heart', 'sweating', 'trembling', 'shortness_of_breath',
            'chest_tightness', 'nausea', 'dizziness', 'muscle_tension',
            'headache', 'fatigue'
        );
        
        $count = rand( 1, 4 );
        return array_slice( $symptoms, 0, $count );
    }
    
    /**
     * Generate random activities
     * 
     * @return array
     */
    private function generate_random_activities() {
        $activities = array(
            'work', 'exercise', 'socializing', 'reading', 'watching_tv',
            'cooking', 'cleaning', 'hobbies', 'relaxing', 'studying'
        );
        
        $count = rand( 2, 5 );
        return array_slice( $activities, 0, $count );
    }
    
    /**
     * Generate accomplishments
     * 
     * @return string
     */
    private function generate_accomplishments() {
        $accomplishments = array(
            'Completed important project at work',
            'Went for a 30-minute walk',
            'Had a good conversation with a friend',
            'Finished reading a chapter',
            'Cooked a healthy meal',
            'Organized my workspace',
            'Practiced meditation for 10 minutes',
            'Helped a colleague with their task'
        );
        
        return $accomplishments[ array_rand( $accomplishments ) ];
    }
    
    /**
     * Generate challenges
     * 
     * @return string
     */
    private function generate_challenges() {
        $challenges = array(
            'Felt overwhelmed by workload',
            'Had difficulty concentrating',
            'Experienced anxiety during meeting',
            'Struggled with decision making',
            'Felt tired despite good sleep',
            'Had conflict with someone',
            'Procrastinated on important task',
            'Felt isolated working from home'
        );
        
        return $challenges[ array_rand( $challenges ) ];
    }
    
    /**
     * Generate gratitude
     * 
     * @return string
     */
    private function generate_gratitude() {
        $gratitude = array(
            'Grateful for supportive family',
            'Thankful for good health',
            'Appreciate having a stable job',
            'Grateful for morning coffee',
            'Thankful for sunny weather',
            'Appreciate my comfortable home',
            'Grateful for understanding friends',
            'Thankful for learning opportunities'
        );
        
        return $gratitude[ array_rand( $gratitude ) ];
    }
    
    /**
     * Generate user goals
     * 
     * @param int $user_id
     * @param array $user_data
     */
    private function generate_user_goals( $user_id, $user_data ) {
        $goals = array(
            array(
                'title' => 'Reduce Overthinking Episodes',
                'description' => 'Track and reduce overthinking episodes to less than 2 per day',
                'target_date' => date( 'Y-m-d', strtotime( '+30 days' ) ),
                'milestones' => array(
                    'Week 1: Track all episodes consistently',
                    'Week 2: Identify main triggers',
                    'Week 3: Practice coping strategies',
                    'Week 4: Achieve target reduction'
                ),
                'progress' => rand( 20, 80 )
            ),
            array(
                'title' => 'Improve Sleep Quality',
                'description' => 'Achieve consistent 7+ hours of quality sleep',
                'target_date' => date( 'Y-m-d', strtotime( '+45 days' ) ),
                'milestones' => array(
                    'Establish bedtime routine',
                    'Reduce screen time before bed',
                    'Track sleep patterns for 2 weeks',
                    'Implement sleep hygiene practices'
                ),
                'progress' => rand( 10, 60 )
            ),
            array(
                'title' => 'Daily Mindfulness Practice',
                'description' => 'Practice mindfulness or meditation for 10 minutes daily',
                'target_date' => date( 'Y-m-d', strtotime( '+60 days' ) ),
                'milestones' => array(
                    'Start with 5 minutes daily',
                    'Increase to 10 minutes',
                    'Try different techniques',
                    'Make it a habit'
                ),
                'progress' => rand( 30, 90 )
            )
        );
        
        // Create 1-2 goals per user
        $goal_count = rand( 1, 2 );
        for ( $i = 0; $i < $goal_count; $i++ ) {
            $goal_data = $goals[ $i ];
            
            $goal = array(
                'id' => uniqid( 'goal_' ),
                'user_id' => $user_id,
                'title' => $goal_data['title'],
                'description' => $goal_data['description'],
                'target_date' => $goal_data['target_date'],
                'status' => $goal_data['progress'] >= 100 ? 'completed' : 'active',
                'progress' => min( 100, $goal_data['progress'] ),
                'milestones' => array(),
                'created_at' => date( 'Y-m-d H:i:s', strtotime( '-' . rand( 7, 30 ) . ' days' ) )
            );
            
            // Add milestones
            foreach ( $goal_data['milestones'] as $index => $milestone_title ) {
                $completed = ( $goal_data['progress'] / 100 ) > ( ( $index + 1 ) / count( $goal_data['milestones'] ) );
                $goal['milestones'][] = array(
                    'id' => uniqid( 'milestone_' ),
                    'title' => $milestone_title,
                    'completed' => $completed,
                    'completed_at' => $completed ? date( 'Y-m-d H:i:s', strtotime( '-' . rand( 1, 7 ) . ' days' ) ) : null
                );
            }
            
            // Save goal
            $user_goals = get_user_meta( $user_id, 'spiralengine_goals', true ) ?: array();
            $user_goals[ $goal['id'] ] = $goal;
            update_user_meta( $user_id, 'spiralengine_goals', $user_goals );
        }
    }
    
    /**
     * Generate AI analyses
     */
    private function generate_ai_analyses() {
        foreach ( $this->demo_users as $user_id => $user_data ) {
            if ( in_array( $user_data['tier'], array( 'silver', 'gold', 'platinum' ) ) ) {
                // Create a few AI analysis records
                for ( $i = 0; $i < rand( 2, 5 ); $i++ ) {
                    $days_ago = rand( 1, 14 );
                    
                    $analysis = array(
                        'user_id' => $user_id,
                        'type' => array( 'pattern', 'insight', 'recommendation' )[ rand( 0, 2 ) ],
                        'content' => $this->generate_ai_content( $user_data ),
                        'created_at' => date( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) )
                    );
                    
                    // Store analysis
                    update_user_meta( 
                        $user_id, 
                        'spiralengine_ai_analysis_' . uniqid(), 
                        $analysis 
                    );
                }
            }
        }
    }
    
    /**
     * Generate AI content
     * 
     * @param array $user_data
     * @return string
     */
    private function generate_ai_content( $user_data ) {
        $insights = array(
            'Your overthinking episodes tend to peak during work hours, particularly before important meetings or deadlines.',
            'I\'ve noticed a strong correlation between your sleep quality and next-day anxiety levels.',
            'Your mood ratings show improvement when you engage in physical exercise or social activities.',
            'Triggers related to work performance appear to be your most significant stressor.',
            'Your coping strategies are most effective when applied early in an episode.',
            'Weekend patterns show lower stress levels but higher overthinking about the upcoming week.',
            'Your progress shows consistent improvement when you maintain regular tracking habits.'
        );
        
        return $insights[ array_rand( $insights ) ];
    }
    
    /**
     * Count episodes
     * 
     * @return int
     */
    private function count_episodes() {
        global $wpdb;
        
        $user_ids = array_keys( $this->demo_users );
        if ( empty( $user_ids ) ) {
            return 0;
        }
        
        $placeholders = array_fill( 0, count( $user_ids ), '%d' );
        
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes 
            WHERE user_id IN (" . implode( ',', $placeholders ) . ")",
            $user_ids
        ) );
    }
    
    /**
     * Count goals
     * 
     * @return int
     */
    private function count_goals() {
        $count = 0;
        
        foreach ( $this->demo_users as $user_id => $user_data ) {
            $goals = get_user_meta( $user_id, 'spiralengine_goals', true );
            if ( is_array( $goals ) ) {
                $count += count( $goals );
            }
        }
        
        return $count;
    }
    
    /**
     * Clean up demo content
     */
    public function cleanup() {
        global $wpdb;
        
        // Find all demo users
        $demo_users = get_users( array(
            'meta_key' => 'spiralengine_demo_user',
            'meta_value' => true
        ) );
        
        foreach ( $demo_users as $user ) {
            // Delete episodes
            $wpdb->delete(
                $wpdb->prefix . 'spiralengine_episodes',
                array( 'user_id' => $user->ID ),
                array( '%d' )
            );
            
            // Delete membership
            $wpdb->delete(
                $wpdb->prefix . 'spiralengine_memberships',
                array( 'user_id' => $user->ID ),
                array( '%d' )
            );
            
            // Delete user and all meta
            wp_delete_user( $user->ID );
        }
        
        return count( $demo_users );
    }
}


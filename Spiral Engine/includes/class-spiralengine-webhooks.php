<?php
/**
 * SpiralEngine Webhook System
 * 
 * @package    SpiralEngine
 * @subpackage Core
 * @since      1.0.0
 */

// includes/class-spiralengine-webhooks.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Webhook System Class
 * 
 * Manages webhook creation, delivery, retry logic, and testing
 */
class SpiralEngine_Webhooks {
    
    /**
     * Instance of this class
     * 
     * @var SpiralEngine_Webhooks
     */
    private static $instance = null;
    
    /**
     * Database handler
     * 
     * @var SpiralEngine_Database
     */
    private $database;
    
    /**
     * Available webhook events
     * 
     * @var array
     */
    private $events = array(
        'episode.created' => 'When a new episode is logged',
        'episode.updated' => 'When an episode is updated',
        'episode.deleted' => 'When an episode is deleted',
        'assessment.completed' => 'When an assessment is completed',
        'assessment.high_risk' => 'When a high-risk assessment is detected',
        'insight.generated' => 'When new insights are generated',
        'pattern.detected' => 'When a new pattern is detected',
        'correlation.found' => 'When a new correlation is found',
        'user.registered' => 'When a new user registers',
        'user.updated' => 'When user profile is updated',
        'user.tier_changed' => 'When user membership tier changes',
        'widget.activated' => 'When a widget is activated',
        'widget.deactivated' => 'When a widget is deactivated',
        'system.health_alert' => 'When system health issue is detected'
    );
    
    /**
     * Webhook queue table name
     * 
     * @var string
     */
    private $queue_table;
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        
        $this->database = new SpiralEngine_Database();
        $this->queue_table = $wpdb->prefix . 'spiralengine_webhook_queue';
        
        $this->init_hooks();
    }
    
    /**
     * Get instance
     * 
     * @return SpiralEngine_Webhooks
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook into all events
        foreach (array_keys($this->events) as $event) {
            add_action('spiralengine_' . str_replace('.', '_', $event), array($this, 'trigger_webhooks'), 10, 2);
        }
        
        // Process webhook queue
        add_action('spiralengine_process_webhook_queue', array($this, 'process_queue'));
        
        // Schedule queue processing if not already scheduled
        if (!wp_next_scheduled('spiralengine_process_webhook_queue')) {
            wp_schedule_event(time(), 'spiralengine_webhook_interval', 'spiralengine_process_webhook_queue');
        }
        
        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
    }
    
    /**
     * Add custom cron interval for webhook processing
     * 
     * @param array $schedules Existing schedules
     * @return array
     */
    public function add_cron_interval($schedules) {
        $schedules['spiralengine_webhook_interval'] = array(
            'interval' => 60, // 1 minute
            'display' => __('Every Minute (SpiralEngine Webhooks)', 'spiralengine')
        );
        return $schedules;
    }
    
    /**
     * Create a new webhook
     * 
     * @param array $data Webhook data
     * @return int|false Webhook ID or false on failure
     */
    public function create_webhook($data) {
        global $wpdb;
        
        $webhook_data = array(
            'name' => sanitize_text_field($data['name']),
            'url' => esc_url_raw($data['url']),
            'events' => wp_json_encode($data['events']),
            'secret' => $this->generate_secret($data['secret'] ?? ''),
            'active' => !empty($data['active']) ? 1 : 0,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
            'last_triggered' => null,
            'failure_count' => 0
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'spiralengine_webhooks',
            $webhook_data,
            array('%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d')
        );
        
        if ($result) {
            $webhook_id = $wpdb->insert_id;
            do_action('spiralengine_webhook_created', $webhook_id, $webhook_data);
            return $webhook_id;
        }
        
        return false;
    }
    
    /**
     * Update a webhook
     * 
     * @param int $webhook_id Webhook ID
     * @param array $data Update data
     * @return bool
     */
    public function update_webhook($webhook_id, $data) {
        global $wpdb;
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['url'])) {
            $update_data['url'] = esc_url_raw($data['url']);
        }
        
        if (isset($data['events'])) {
            $update_data['events'] = wp_json_encode($data['events']);
        }
        
        if (isset($data['secret'])) {
            $update_data['secret'] = $this->generate_secret($data['secret']);
        }
        
        if (isset($data['active'])) {
            $update_data['active'] = !empty($data['active']) ? 1 : 0;
        }
        
        $update_data['updated_at'] = current_time('mysql', true);
        
        $result = $wpdb->update(
            $wpdb->prefix . 'spiralengine_webhooks',
            $update_data,
            array('id' => $webhook_id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            do_action('spiralengine_webhook_updated', $webhook_id, $update_data);
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete a webhook
     * 
     * @param int $webhook_id Webhook ID
     * @return bool
     */
    public function delete_webhook($webhook_id) {
        global $wpdb;
        
        // Delete associated queue items
        $wpdb->delete(
            $this->queue_table,
            array('webhook_id' => $webhook_id),
            array('%d')
        );
        
        // Delete webhook
        $result = $wpdb->delete(
            $wpdb->prefix . 'spiralengine_webhooks',
            array('id' => $webhook_id),
            array('%d')
        );
        
        if ($result) {
            do_action('spiralengine_webhook_deleted', $webhook_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get webhook by ID
     * 
     * @param int $webhook_id Webhook ID
     * @return array|null
     */
    public function get_webhook($webhook_id) {
        global $wpdb;
        
        $webhook = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_webhooks WHERE id = %d",
            $webhook_id
        ), ARRAY_A);
        
        if ($webhook) {
            $webhook['events'] = json_decode($webhook['events'], true);
            return $webhook;
        }
        
        return null;
    }
    
    /**
     * Get all webhooks
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function get_webhooks($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'active_only' => false,
            'event' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if ($args['active_only']) {
            $where[] = 'active = 1';
        }
        
        if ($args['event']) {
            $where[] = $wpdb->prepare('events LIKE %s', '%"' . $args['event'] . '"%');
        }
        
        $where_clause = implode(' AND ', $where);
        
        $webhooks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_webhooks 
            WHERE {$where_clause}
            ORDER BY {$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        ), ARRAY_A);
        
        foreach ($webhooks as &$webhook) {
            $webhook['events'] = json_decode($webhook['events'], true);
        }
        
        return $webhooks;
    }
    
    /**
     * Trigger webhooks for an event
     * 
     * @param mixed $data Event data
     * @param string $event Event name (optional if called via action)
     */
    public function trigger_webhooks($data, $event = null) {
        // If called via action, get event from current filter
        if ($event === null) {
            $current_filter = current_filter();
            $event = str_replace(array('spiralengine_', '_'), array('', '.'), $current_filter);
        }
        
        // Get active webhooks for this event
        $webhooks = $this->get_webhooks_for_event($event);
        
        if (empty($webhooks)) {
            return;
        }
        
        // Prepare payload
        $payload = $this->prepare_payload($event, $data);
        
        // Queue webhook deliveries
        foreach ($webhooks as $webhook) {
            $this->queue_webhook($webhook['id'], $payload);
        }
    }
    
    /**
     * Queue a webhook for delivery
     * 
     * @param int $webhook_id Webhook ID
     * @param array $payload Webhook payload
     * @return bool
     */
    private function queue_webhook($webhook_id, $payload) {
        global $wpdb;
        
        $queue_data = array(
            'webhook_id' => $webhook_id,
            'payload' => wp_json_encode($payload),
            'attempts' => 0,
            'status' => 'pending',
            'created_at' => current_time('mysql', true),
            'next_attempt' => current_time('mysql', true)
        );
        
        $result = $wpdb->insert(
            $this->queue_table,
            $queue_data,
            array('%d', '%s', '%d', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Process webhook queue
     */
    public function process_queue() {
        global $wpdb;
        
        // Get pending webhooks
        $queue_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->queue_table} 
            WHERE status IN ('pending', 'failed') 
            AND next_attempt <= %s 
            AND attempts < 5 
            ORDER BY created_at ASC 
            LIMIT 10",
            current_time('mysql', true)
        ), ARRAY_A);
        
        foreach ($queue_items as $item) {
            $this->deliver_webhook($item);
        }
    }
    
    /**
     * Deliver a webhook
     * 
     * @param array $queue_item Queue item data
     */
    private function deliver_webhook($queue_item) {
        global $wpdb;
        
        // Get webhook details
        $webhook = $this->get_webhook($queue_item['webhook_id']);
        
        if (!$webhook || !$webhook['active']) {
            // Mark as cancelled if webhook doesn't exist or is inactive
            $wpdb->update(
                $this->queue_table,
                array('status' => 'cancelled'),
                array('id' => $queue_item['id']),
                array('%s'),
                array('%d')
            );
            return;
        }
        
        // Prepare request
        $payload = json_decode($queue_item['payload'], true);
        $headers = $this->prepare_headers($webhook, $payload);
        
        $args = array(
            'body' => wp_json_encode($payload),
            'headers' => $headers,
            'timeout' => 15,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'sslverify' => true
        );
        
        // Make request
        $response = wp_remote_post($webhook['url'], $args);
        
        // Update attempt count
        $wpdb->update(
            $this->queue_table,
            array('attempts' => $queue_item['attempts'] + 1),
            array('id' => $queue_item['id']),
            array('%d'),
            array('%d')
        );
        
        // Handle response
        if (is_wp_error($response)) {
            $this->handle_webhook_failure($queue_item, $webhook, $response->get_error_message());
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code >= 200 && $status_code < 300) {
                $this->handle_webhook_success($queue_item, $webhook, $response);
            } else {
                $response_body = wp_remote_retrieve_body($response);
                $this->handle_webhook_failure($queue_item, $webhook, "HTTP {$status_code}: {$response_body}");
            }
        }
    }
    
    /**
     * Handle successful webhook delivery
     * 
     * @param array $queue_item Queue item data
     * @param array $webhook Webhook data
     * @param array $response HTTP response
     */
    private function handle_webhook_success($queue_item, $webhook, $response) {
        global $wpdb;
        
        // Update queue item
        $wpdb->update(
            $this->queue_table,
            array(
                'status' => 'delivered',
                'delivered_at' => current_time('mysql', true),
                'response_code' => wp_remote_retrieve_response_code($response),
                'response_body' => wp_remote_retrieve_body($response)
            ),
            array('id' => $queue_item['id']),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        // Update webhook last triggered time and reset failure count
        $wpdb->update(
            $wpdb->prefix . 'spiralengine_webhooks',
            array(
                'last_triggered' => current_time('mysql', true),
                'failure_count' => 0,
                'last_status' => 'success'
            ),
            array('id' => $webhook['id']),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        // Log success
        $this->log_webhook_event($webhook['id'], 'delivered', array(
            'queue_id' => $queue_item['id'],
            'response_code' => wp_remote_retrieve_response_code($response)
        ));
    }
    
    /**
     * Handle failed webhook delivery
     * 
     * @param array $queue_item Queue item data
     * @param array $webhook Webhook data
     * @param string $error_message Error message
     */
    private function handle_webhook_failure($queue_item, $webhook, $error_message) {
        global $wpdb;
        
        $attempts = $queue_item['attempts'] + 1;
        
        if ($attempts >= 5) {
            // Max attempts reached, mark as failed
            $wpdb->update(
                $this->queue_table,
                array(
                    'status' => 'failed',
                    'error_message' => $error_message
                ),
                array('id' => $queue_item['id']),
                array('%s', '%s'),
                array('%d')
            );
            
            // Update webhook failure count
            $wpdb->update(
                $wpdb->prefix . 'spiralengine_webhooks',
                array(
                    'failure_count' => $webhook['failure_count'] + 1,
                    'last_status' => 'failed',
                    'last_error' => $error_message
                ),
                array('id' => $webhook['id']),
                array('%d', '%s', '%s'),
                array('%d')
            );
            
            // Disable webhook if too many failures
            if ($webhook['failure_count'] + 1 >= 10) {
                $this->update_webhook($webhook['id'], array('active' => false));
                $this->notify_webhook_disabled($webhook);
            }
        } else {
            // Schedule retry with exponential backoff
            $next_attempt = $this->calculate_next_attempt($attempts);
            
            $wpdb->update(
                $this->queue_table,
                array(
                    'status' => 'failed',
                    'next_attempt' => $next_attempt,
                    'error_message' => $error_message
                ),
                array('id' => $queue_item['id']),
                array('%s', '%s', '%s'),
                array('%d')
            );
        }
        
        // Log failure
        $this->log_webhook_event($webhook['id'], 'failed', array(
            'queue_id' => $queue_item['id'],
            'attempts' => $attempts,
            'error' => $error_message
        ));
    }
    
    /**
     * Test a webhook
     * 
     * @param int $webhook_id Webhook ID
     * @return array Test result
     */
    public function test_webhook($webhook_id) {
        $webhook = $this->get_webhook($webhook_id);
        
        if (!$webhook) {
            return array(
                'success' => false,
                'message' => __('Webhook not found', 'spiralengine')
            );
        }
        
        // Prepare test payload
        $payload = $this->prepare_payload('test.webhook', array(
            'message' => 'This is a test webhook from SpiralEngine',
            'webhook_id' => $webhook_id,
            'timestamp' => current_time('c', true)
        ));
        
        $headers = $this->prepare_headers($webhook, $payload);
        
        $args = array(
            'body' => wp_json_encode($payload),
            'headers' => $headers,
            'timeout' => 15,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'sslverify' => true
        );
        
        // Make test request
        $start_time = microtime(true);
        $response = wp_remote_post($webhook['url'], $args);
        $duration = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'duration' => $duration
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $success = $status_code >= 200 && $status_code < 300;
        
        // Log test
        $this->log_webhook_event($webhook_id, 'tested', array(
            'success' => $success,
            'status_code' => $status_code,
            'duration' => $duration
        ));
        
        return array(
            'success' => $success,
            'status_code' => $status_code,
            'response' => $response_body,
            'duration' => $duration,
            'headers_sent' => $headers
        );
    }
    
    /**
     * Get webhooks for a specific event
     * 
     * @param string $event Event name
     * @return array
     */
    private function get_webhooks_for_event($event) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_webhooks 
            WHERE active = 1 
            AND events LIKE %s",
            '%"' . $event . '"%'
        ), ARRAY_A);
    }
    
    /**
     * Prepare webhook payload
     * 
     * @param string $event Event name
     * @param mixed $data Event data
     * @return array
     */
    private function prepare_payload($event, $data) {
        return array(
            'event' => $event,
            'data' => $data,
            'timestamp' => current_time('c', true),
            'site' => array(
                'url' => home_url(),
                'name' => get_bloginfo('name')
            )
        );
    }
    
    /**
     * Prepare webhook headers
     * 
     * @param array $webhook Webhook data
     * @param array $payload Payload data
     * @return array
     */
    private function prepare_headers($webhook, $payload) {
        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'SpiralEngine-Webhook/' . SPIRALENGINE_VERSION,
            'X-SpiralEngine-Event' => $payload['event'],
            'X-SpiralEngine-Delivery' => uniqid('spiralengine_', true)
        );
        
        // Add signature if secret is set
        if (!empty($webhook['secret'])) {
            $signature = hash_hmac('sha256', wp_json_encode($payload), $webhook['secret']);
            $headers['X-SpiralEngine-Signature'] = 'sha256=' . $signature;
        }
        
        return $headers;
    }
    
    /**
     * Generate or hash webhook secret
     * 
     * @param string $secret Optional secret
     * @return string
     */
    private function generate_secret($secret = '') {
        if (empty($secret)) {
            return wp_generate_password(32, false);
        }
        return $secret;
    }
    
    /**
     * Calculate next retry attempt time
     * 
     * @param int $attempts Number of attempts
     * @return string MySQL timestamp
     */
    private function calculate_next_attempt($attempts) {
        // Exponential backoff: 1min, 5min, 15min, 30min
        $delays = array(60, 300, 900, 1800);
        $delay = isset($delays[$attempts - 1]) ? $delays[$attempts - 1] : 3600;
        
        return date('Y-m-d H:i:s', time() + $delay);
    }
    
    /**
     * Log webhook event
     * 
     * @param int $webhook_id Webhook ID
     * @param string $event Event type
     * @param array $data Event data
     */
    private function log_webhook_event($webhook_id, $event, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_webhook_logs',
            array(
                'webhook_id' => $webhook_id,
                'event' => $event,
                'data' => wp_json_encode($data),
                'created_at' => current_time('mysql', true)
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Notify admin when webhook is disabled due to failures
     * 
     * @param array $webhook Webhook data
     */
    private function notify_webhook_disabled($webhook) {
        $admin_email = get_option('admin_email');
        $subject = sprintf(__('[%s] Webhook Disabled Due to Failures', 'spiralengine'), get_bloginfo('name'));
        
        $message = sprintf(
            __("The following webhook has been automatically disabled due to repeated failures:\n\n" .
               "Name: %s\n" .
               "URL: %s\n" .
               "Events: %s\n" .
               "Failure Count: %d\n" .
               "Last Error: %s\n\n" .
               "Please check the webhook configuration and re-enable it when the issue is resolved.",
               'spiralengine'),
            $webhook['name'],
            $webhook['url'],
            implode(', ', $webhook['events']),
            $webhook['failure_count'],
            $webhook['last_error'] ?? 'Unknown'
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get available events
     * 
     * @return array
     */
    public function get_available_events() {
        return $this->events;
    }
    
    /**
     * Get webhook statistics
     * 
     * @param int $webhook_id Webhook ID
     * @param string $period Time period (day, week, month)
     * @return array
     */
    public function get_webhook_stats($webhook_id, $period = 'week') {
        global $wpdb;
        
        $date_query = $this->get_date_query($period);
        
        // Get delivery stats
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_deliveries,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(CASE WHEN status = 'delivered' THEN attempts ELSE NULL END) as avg_attempts
            FROM {$this->queue_table}
            WHERE webhook_id = %d
            AND created_at >= %s",
            $webhook_id,
            $date_query
        ), ARRAY_A);
        
        // Get response time stats
        $response_times = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, delivered_at))
            FROM {$this->queue_table}
            WHERE webhook_id = %d
            AND status = 'delivered'
            AND created_at >= %s",
            $webhook_id,
            $date_query
        ));
        
        $stats['avg_response_time'] = $response_times ?: 0;
        $stats['success_rate'] = $stats['total_deliveries'] > 0 
            ? round(($stats['successful'] / $stats['total_deliveries']) * 100, 2) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Get date query for statistics
     * 
     * @param string $period Time period
     * @return string
     */
    private function get_date_query($period) {
        switch ($period) {
            case 'day':
                return date('Y-m-d H:i:s', strtotime('-1 day'));
            case 'week':
                return date('Y-m-d H:i:s', strtotime('-1 week'));
            case 'month':
                return date('Y-m-d H:i:s', strtotime('-1 month'));
            default:
                return date('Y-m-d H:i:s', strtotime('-1 week'));
        }
    }
    
    /**
     * Clean old webhook logs and queue items
     * 
     * @param int $days Days to keep
     */
    public function cleanup_old_data($days = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Delete old queue items
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->queue_table} 
            WHERE created_at < %s 
            AND status IN ('delivered', 'cancelled')",
            $cutoff_date
        ));
        
        // Delete old logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}spiralengine_webhook_logs 
            WHERE created_at < %s",
            $cutoff_date
        ));
    }
}

// Webhook Controller for REST API
class SpiralEngine_Webhooks_Controller extends SpiralEngine_REST_Controller {
    
    /**
     * Webhook handler instance
     * 
     * @var SpiralEngine_Webhooks
     */
    private $webhooks;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->webhooks = SpiralEngine_Webhooks::get_instance();
        $this->rest_base = 'webhooks';
    }
    
    /**
     * Check admin permissions
     * 
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function admin_permissions_check($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'spiralengine_rest_forbidden',
                __('You do not have permission to manage webhooks', 'spiralengine'),
                array('status' => 403)
            );
        }
        
        return parent::permissions_check($request);
    }
    
    /**
     * Get webhooks
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_items($request) {
        $args = array(
            'active_only' => $request->get_param('active_only'),
            'event' => $request->get_param('event'),
            'limit' => $request->get_param('per_page'),
            'offset' => ($request->get_param('page') - 1) * $request->get_param('per_page')
        );
        
        $webhooks = $this->webhooks->get_webhooks($args);
        
        // Add statistics to each webhook
        foreach ($webhooks as &$webhook) {
            $webhook['stats'] = $this->webhooks->get_webhook_stats($webhook['id'], 'week');
        }
        
        return new WP_REST_Response($this->format_response($webhooks), 200);
    }
    
    /**
     * Get single webhook
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_item($request) {
        $webhook = $this->webhooks->get_webhook($request->get_param('id'));
        
        if (!$webhook) {
            return $this->format_error('not_found', __('Webhook not found', 'spiralengine'), 404);
        }
        
        $webhook['stats'] = $this->webhooks->get_webhook_stats($webhook['id'], 'month');
        
        return new WP_REST_Response($this->format_response($webhook), 200);
    }
    
    /**
     * Create webhook
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function create_item($request) {
        $data = array(
            'name' => $request->get_param('name'),
            'url' => $request->get_param('url'),
            'events' => $request->get_param('events'),
            'secret' => $request->get_param('secret'),
            'active' => $request->get_param('active')
        );
        
        $webhook_id = $this->webhooks->create_webhook($data);
        
        if (!$webhook_id) {
            return $this->format_error('creation_failed', __('Failed to create webhook', 'spiralengine'), 500);
        }
        
        $webhook = $this->webhooks->get_webhook($webhook_id);
        
        return new WP_REST_Response($this->format_response($webhook), 201);
    }
    
    /**
     * Update webhook
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function update_item($request) {
        $webhook_id = $request->get_param('id');
        
        if (!$this->webhooks->get_webhook($webhook_id)) {
            return $this->format_error('not_found', __('Webhook not found', 'spiralengine'), 404);
        }
        
        $data = array();
        
        foreach (array('name', 'url', 'events', 'secret', 'active') as $field) {
            if ($request->has_param($field)) {
                $data[$field] = $request->get_param($field);
            }
        }
        
        $result = $this->webhooks->update_webhook($webhook_id, $data);
        
        if (!$result) {
            return $this->format_error('update_failed', __('Failed to update webhook', 'spiralengine'), 500);
        }
        
        $webhook = $this->webhooks->get_webhook($webhook_id);
        
        return new WP_REST_Response($this->format_response($webhook), 200);
    }
    
    /**
     * Delete webhook
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function delete_item($request) {
        $webhook_id = $request->get_param('id');
        
        if (!$this->webhooks->get_webhook($webhook_id)) {
            return $this->format_error('not_found', __('Webhook not found', 'spiralengine'), 404);
        }
        
        $result = $this->webhooks->delete_webhook($webhook_id);
        
        if (!$result) {
            return $this->format_error('delete_failed', __('Failed to delete webhook', 'spiralengine'), 500);
        }
        
        return new WP_REST_Response($this->format_response(array('deleted' => true)), 200);
    }
    
    /**
     * Test webhook
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function test_webhook($request) {
        $webhook_id = $request->get_param('id');
        
        if (!$this->webhooks->get_webhook($webhook_id)) {
            return $this->format_error('not_found', __('Webhook not found', 'spiralengine'), 404);
        }
        
        $result = $this->webhooks->test_webhook($webhook_id);
        
        return new WP_REST_Response($this->format_response($result), 200);
    }
}

// Initialize webhook system
SpiralEngine_Webhooks::get_instance();

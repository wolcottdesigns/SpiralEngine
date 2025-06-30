<?php
// includes/class-spiralengine-performance.php

/**
 * Spiral Engine Performance Optimization
 * 
 * Handles query optimization, caching layer, asset management,
 * and performance monitoring.
 *
 * @package    Spiral_Engine
 * @subpackage Spiral_Engine/includes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPIRALENGINE_Performance {
    
    /**
     * Cache instance
     */
    private $cache;
    
    /**
     * Time manager instance
     */
    private $time_manager;
    
    /**
     * Performance settings
     */
    private $settings;
    
    /**
     * Query monitor
     */
    private $query_monitor = array();
    
    /**
     * Asset optimizer
     */
    private $asset_optimizer;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->load_settings();
        $this->init_cache();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Query optimization
        add_filter('posts_request', array($this, 'optimize_query'), 10, 2);
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Caching
        add_action('init', array($this, 'init_page_cache'));
        add_action('spiralengine_cache_purge', array($this, 'purge_cache'));
        
        // Asset optimization
        add_action('wp_enqueue_scripts', array($this, 'optimize_assets'), 999);
        add_action('admin_enqueue_scripts', array($this, 'optimize_admin_assets'), 999);
        
        // Performance monitoring
        add_action('init', array($this, 'start_monitoring'));
        add_action('shutdown', array($this, 'end_monitoring'));
        
        // Database optimization
        add_filter('pre_get_posts', array($this, 'optimize_wp_query'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_get_performance_stats', array($this, 'ajax_get_performance_stats'));
        add_action('wp_ajax_spiralengine_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_spiralengine_optimize_database', array($this, 'ajax_optimize_database'));
        
        // Lazy loading
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazy_loading'), 10, 3);
        
        // Preloading
        add_action('wp_head', array($this, 'add_resource_hints'), 1);
    }
    
    /**
     * Load settings
     */
    private function load_settings() {
        $this->settings = array(
            'cache_enabled' => get_option('spiralengine_cache_enabled', true),
            'cache_ttl' => get_option('spiralengine_cache_ttl', 3600),
            'query_cache' => get_option('spiralengine_query_cache', true),
            'object_cache' => get_option('spiralengine_object_cache', true),
            'page_cache' => get_option('spiralengine_page_cache', false),
            'minify_assets' => get_option('spiralengine_minify_assets', true),
            'combine_assets' => get_option('spiralengine_combine_assets', false),
            'lazy_loading' => get_option('spiralengine_lazy_loading', true),
            'preload_critical' => get_option('spiralengine_preload_critical', true),
            'monitor_queries' => get_option('spiralengine_monitor_queries', true),
            'slow_query_threshold' => get_option('spiralengine_slow_query_threshold', 1.0)
        );
    }
    
    /**
     * Initialize cache
     */
    private function init_cache() {
        // Use object cache if available
        if (wp_using_ext_object_cache()) {
            $this->cache = new SPIRALENGINE_Object_Cache();
        } else {
            $this->cache = new SPIRALENGINE_Transient_Cache();
        }
    }
    
    /**
     * Start performance monitoring
     */
    public function start_monitoring() {
        if (!$this->settings['monitor_queries']) {
            return;
        }
        
        // Record start time
        if (!defined('SPIRALENGINE_START_TIME')) {
            define('SPIRALENGINE_START_TIME', microtime(true));
        }
        
        // Monitor database queries
        if (defined('SAVEQUERIES') && SAVEQUERIES) {
            add_filter('query', array($this, 'log_query'));
        }
    }
    
    /**
     * End performance monitoring
     */
    public function end_monitoring() {
        if (!$this->settings['monitor_queries'] || !defined('SPIRALENGINE_START_TIME')) {
            return;
        }
        
        $total_time = microtime(true) - SPIRALENGINE_START_TIME;
        $memory_peak = memory_get_peak_usage(true);
        
        // Log performance metrics
        $this->log_performance_metrics(array(
            'total_time' => $total_time,
            'memory_peak' => $memory_peak,
            'queries' => $this->query_monitor,
            'cache_hits' => $this->cache->get_stats()
        ));
    }
    
    /**
     * Optimize WordPress query
     */
    public function optimize_wp_query($query) {
        if (!$query->is_main_query() || is_admin()) {
            return;
        }
        
        // Disable unnecessary queries
        if (!is_singular()) {
            $query->set('no_found_rows', true);
        }
        
        // Optimize post meta queries
        if ($query->is_single() || $query->is_page()) {
            $query->set('update_post_meta_cache', false);
            $query->set('update_post_term_cache', false);
        }
        
        // Limit fields for archive pages
        if ($query->is_archive() || $query->is_search()) {
            $query->set('fields', 'ids');
        }
    }
    
    /**
     * Optimize database query
     */
    public function optimize_query($request, $query) {
        global $wpdb;
        
        // Skip non-SPIRAL Engine queries
        if (strpos($request, 'spiralengine') === false) {
            return $request;
        }
        
        // Cache key
        $cache_key = 'spiralengine_query_' . md5($request);
        
        // Check cache
        if ($this->settings['query_cache']) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Optimize SELECT queries
        if (stripos($request, 'SELECT') === 0) {
            // Add SQL_CALC_FOUND_ROWS only when necessary
            if (!$query->get('no_found_rows') && stripos($request, 'SQL_CALC_FOUND_ROWS') === false) {
                $request = preg_replace('/^SELECT/', 'SELECT SQL_CALC_FOUND_ROWS', $request);
            }
            
            // Force index usage for specific tables
            $indexed_tables = array(
                'spiralengine_assessments' => 'idx_user_created',
                'spiralengine_episodes_overthinking' => 'idx_user_date',
                'spiralengine_user_activity' => 'idx_user_created'
            );
            
            foreach ($indexed_tables as $table => $index) {
                if (strpos($request, $wpdb->prefix . $table) !== false) {
                    $request = str_replace(
                        'FROM ' . $wpdb->prefix . $table,
                        'FROM ' . $wpdb->prefix . $table . ' FORCE INDEX (' . $index . ')',
                        $request
                    );
                }
            }
        }
        
        // Cache the optimized query
        if ($this->settings['query_cache']) {
            $this->cache->set($cache_key, $request, $this->settings['cache_ttl']);
        }
        
        return $request;
    }
    
    /**
     * Cache implementation classes
     */
    class SPIRALENGINE_Object_Cache {
        public function get($key) {
            return wp_cache_get($key, 'spiralengine');
        }
        
        public function set($key, $value, $expire = 0) {
            return wp_cache_set($key, $value, 'spiralengine', $expire);
        }
        
        public function delete($key) {
            return wp_cache_delete($key, 'spiralengine');
        }
        
        public function flush() {
            return wp_cache_flush();
        }
        
        public function get_stats() {
            global $wp_object_cache;
            
            if (method_exists($wp_object_cache, 'stats')) {
                return $wp_object_cache->stats();
            }
            
            return array(
                'hits' => 0,
                'misses' => 0,
                'ratio' => 0
            );
        }
    }
    
    class SPIRALENGINE_Transient_Cache {
        public function get($key) {
            return get_transient($key);
        }
        
        public function set($key, $value, $expire = 0) {
            return set_transient($key, $value, $expire);
        }
        
        public function delete($key) {
            return delete_transient($key);
        }
        
        public function flush() {
            global $wpdb;
            
            return $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_spiralengine_%' 
                 OR option_name LIKE '_transient_timeout_spiralengine_%'"
            );
        }
        
        public function get_stats() {
            // Basic stats for transient cache
            return array(
                'hits' => get_option('spiralengine_cache_hits', 0),
                'misses' => get_option('spiralengine_cache_misses', 0),
                'ratio' => 0
            );
        }
    }
    
    /**
     * Page cache implementation
     */
    public function init_page_cache() {
        if (!$this->settings['page_cache'] || is_admin() || is_user_logged_in()) {
            return;
        }
        
        // Check if page is cacheable
        if ($this->is_cacheable_page()) {
            $cache_key = $this->get_page_cache_key();
            $cached_page = $this->cache->get($cache_key);
            
            if ($cached_page !== false) {
                // Serve cached page
                echo $cached_page;
                echo "\n<!-- Served from SPIRAL Engine cache -->";
                exit;
            } else {
                // Start output buffering to cache page
                ob_start(array($this, 'cache_page_output'));
            }
        }
    }
    
    /**
     * Cache page output
     */
    public function cache_page_output($buffer) {
        if (!$this->is_cacheable_page()) {
            return $buffer;
        }
        
        $cache_key = $this->get_page_cache_key();
        $this->cache->set($cache_key, $buffer, $this->settings['cache_ttl']);
        
        return $buffer;
    }
    
    /**
     * Check if page is cacheable
     */
    private function is_cacheable_page() {
        // Don't cache POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return false;
        }
        
        // Don't cache pages with query strings (except specific ones)
        $allowed_params = array('page', 'paged');
        $query_string = $_SERVER['QUERY_STRING'];
        
        if (!empty($query_string)) {
            parse_str($query_string, $params);
            foreach ($params as $key => $value) {
                if (!in_array($key, $allowed_params)) {
                    return false;
                }
            }
        }
        
        // Don't cache specific pages
        if (is_404() || is_search() || is_preview()) {
            return false;
        }
        
        // Check if SPIRAL Engine page
        if (!$this->is_spiralengine_page()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get page cache key
     */
    private function get_page_cache_key() {
        $url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        return 'spiralengine_page_' . md5($url);
    }
    
    /**
     * Check if SPIRAL Engine page
     */
    private function is_spiralengine_page() {
        // Check if it's a SPIRAL Engine shortcode page
        global $post;
        
        if ($post && has_shortcode($post->post_content, 'spiralengine')) {
            return true;
        }
        
        // Check if it's a SPIRAL Engine custom post type
        if (is_singular('spiralengine_widget')) {
            return true;
        }
        
        // Check URL patterns
        $uri = $_SERVER['REQUEST_URI'];
        $patterns = array(
            '/spiral-engine/',
            '/spiralengine/',
            '/dashboard/',
            '/assessments/'
        );
        
        foreach ($patterns as $pattern) {
            if (strpos($uri, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Optimize assets
     */
    public function optimize_assets() {
        if (!$this->settings['minify_assets']) {
            return;
        }
        
        global $wp_scripts, $wp_styles;
        
        // Optimize scripts
        foreach ($wp_scripts->registered as $handle => $script) {
            if (strpos($handle, 'spiralengine') !== false) {
                // Use minified version if available
                if (strpos($script->src, '.min.js') === false) {
                    $min_src = str_replace('.js', '.min.js', $script->src);
                    if (file_exists(str_replace(home_url(), ABSPATH, $min_src))) {
                        $wp_scripts->registered[$handle]->src = $min_src;
                    }
                }
                
                // Add defer attribute
                $wp_scripts->registered[$handle]->extra['defer'] = true;
            }
        }
        
        // Optimize styles
        foreach ($wp_styles->registered as $handle => $style) {
            if (strpos($handle, 'spiralengine') !== false) {
                // Use minified version if available
                if (strpos($style->src, '.min.css') === false) {
                    $min_src = str_replace('.css', '.min.css', $style->src);
                    if (file_exists(str_replace(home_url(), ABSPATH, $min_src))) {
                        $wp_styles->registered[$handle]->src = $min_src;
                    }
                }
            }
        }
        
        // Combine assets if enabled
        if ($this->settings['combine_assets']) {
            $this->combine_assets();
        }
    }
    
    /**
     * Combine assets
     */
    private function combine_assets() {
        // Group SPIRAL Engine scripts
        $scripts_to_combine = array();
        $styles_to_combine = array();
        
        global $wp_scripts, $wp_styles;
        
        foreach ($wp_scripts->queue as $handle) {
            if (strpos($handle, 'spiralengine') !== false && $handle !== 'spiralengine-combined') {
                $scripts_to_combine[] = $handle;
            }
        }
        
        foreach ($wp_styles->queue as $handle) {
            if (strpos($handle, 'spiralengine') !== false && $handle !== 'spiralengine-combined') {
                $styles_to_combine[] = $handle;
            }
        }
        
        // Combine scripts
        if (count($scripts_to_combine) > 1) {
            $combined_js = $this->combine_files($scripts_to_combine, 'js');
            if ($combined_js) {
                // Dequeue individual scripts
                foreach ($scripts_to_combine as $handle) {
                    wp_dequeue_script($handle);
                }
                
                // Enqueue combined script
                wp_enqueue_script('spiralengine-combined', $combined_js, array(), SPIRALENGINE_VERSION, true);
            }
        }
        
        // Combine styles
        if (count($styles_to_combine) > 1) {
            $combined_css = $this->combine_files($styles_to_combine, 'css');
            if ($combined_css) {
                // Dequeue individual styles
                foreach ($styles_to_combine as $handle) {
                    wp_dequeue_style($handle);
                }
                
                // Enqueue combined style
                wp_enqueue_style('spiralengine-combined', $combined_css, array(), SPIRALENGINE_VERSION);
            }
        }
    }
    
    /**
     * Combine files
     */
    private function combine_files($handles, $type) {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/spiralengine-cache/';
        
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        // Generate cache key
        $cache_key = md5(implode('', $handles) . SPIRALENGINE_VERSION);
        $cache_file = $cache_dir . $cache_key . '.' . $type;
        $cache_url = $upload_dir['baseurl'] . '/spiralengine-cache/' . $cache_key . '.' . $type;
        
        // Check if combined file exists
        if (file_exists($cache_file)) {
            return $cache_url;
        }
        
        // Combine files
        $combined_content = '';
        
        if ($type === 'js') {
            global $wp_scripts;
            foreach ($handles as $handle) {
                if (isset($wp_scripts->registered[$handle])) {
                    $src = $wp_scripts->registered[$handle]->src;
                    $file_path = str_replace(home_url(), ABSPATH, $src);
                    
                    if (file_exists($file_path)) {
                        $combined_content .= "\n/* SPIRAL Engine: $handle */\n";
                        $combined_content .= file_get_contents($file_path);
                        $combined_content .= "\n";
                    }
                }
            }
        } else {
            global $wp_styles;
            foreach ($handles as $handle) {
                if (isset($wp_styles->registered[$handle])) {
                    $src = $wp_styles->registered[$handle]->src;
                    $file_path = str_replace(home_url(), ABSPATH, $src);
                    
                    if (file_exists($file_path)) {
                        $combined_content .= "\n/* SPIRAL Engine: $handle */\n";
                        $content = file_get_contents($file_path);
                        
                        // Fix relative URLs in CSS
                        $content = $this->fix_css_urls($content, dirname($src));
                        
                        $combined_content .= $content;
                        $combined_content .= "\n";
                    }
                }
            }
        }
        
        // Save combined file
        if (!empty($combined_content)) {
            file_put_contents($cache_file, $combined_content);
            return $cache_url;
        }
        
        return false;
    }
    
    /**
     * Fix CSS URLs
     */
    private function fix_css_urls($css, $base_path) {
        // Convert relative URLs to absolute
        $css = preg_replace_callback('/url\([\'"]?(?!data:|https?:|\/\/)([^\'"\)]+)[\'"]?\)/i', function($matches) use ($base_path) {
            $url = $matches[1];
            $absolute_url = $base_path . '/' . $url;
            return 'url("' . $absolute_url . '")';
        }, $css);
        
        return $css;
    }
    
    /**
     * Add lazy loading
     */
    public function add_lazy_loading($attributes, $attachment, $size) {
        if (!$this->settings['lazy_loading']) {
            return $attributes;
        }
        
        // Skip if already has loading attribute
        if (isset($attributes['loading'])) {
            return $attributes;
        }
        
        // Add lazy loading
        $attributes['loading'] = 'lazy';
        
        // Add placeholder for smooth loading
        if (!isset($attributes['data-src'])) {
            $attributes['data-src'] = $attributes['src'];
            $attributes['src'] = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E';
            $attributes['class'] = (isset($attributes['class']) ? $attributes['class'] . ' ' : '') . 'spiralengine-lazy';
        }
        
        return $attributes;
    }
    
    /**
     * Add resource hints
     */
    public function add_resource_hints() {
        if (!$this->settings['preload_critical']) {
            return;
        }
        
        // Preconnect to external domains
        $domains = array(
            'https://api.openai.com',
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com'
        );
        
        foreach ($domains as $domain) {
            echo '<link rel="preconnect" href="' . esc_url($domain) . '" crossorigin>' . "\n";
        }
        
        // Preload critical assets
        $critical_assets = array(
            array(
                'href' => SPIRALENGINE_PLUGIN_URL . 'assets/css/spiralengine-critical.min.css',
                'as' => 'style'
            ),
            array(
                'href' => SPIRALENGINE_PLUGIN_URL . 'assets/js/spiralengine-core.min.js',
                'as' => 'script'
            )
        );
        
        foreach ($critical_assets as $asset) {
            if (file_exists(str_replace(SPIRALENGINE_PLUGIN_URL, SPIRALENGINE_PLUGIN_DIR, $asset['href']))) {
                echo '<link rel="preload" href="' . esc_url($asset['href']) . '" as="' . esc_attr($asset['as']) . '">' . "\n";
            }
        }
        
        // DNS prefetch for API endpoints
        echo '<link rel="dns-prefetch" href="//api.openai.com">' . "\n";
    }
    
    /**
     * Get performance statistics
     */
    public function get_performance_stats() {
        global $wpdb;
        
        $stats = array(
            'current' => array(),
            'historical' => array(),
            'recommendations' => array()
        );
        
        // Current performance
        $stats['current'] = array(
            'page_load_time' => $this->get_current_page_load_time(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'db_queries' => get_num_queries(),
            'cache_hit_rate' => $this->calculate_cache_hit_rate()
        );
        
        // Historical data (last 24 hours)
        $historical = $wpdb->get_results(
            "SELECT 
                AVG(page_load_time) as avg_load_time,
                MAX(page_load_time) as max_load_time,
                AVG(memory_usage) as avg_memory,
                AVG(db_queries) as avg_queries
             FROM {$wpdb->prefix}spiralengine_performance_log
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        if (!empty($historical)) {
            $stats['historical'] = array(
                'avg_load_time' => round($historical[0]->avg_load_time, 3),
                'max_load_time' => round($historical[0]->max_load_time, 3),
                'avg_memory' => $historical[0]->avg_memory,
                'avg_queries' => round($historical[0]->avg_queries)
            );
        }
        
        // Slow queries
        $slow_queries = $wpdb->get_results(
            "SELECT query, execution_time, COUNT(*) as count
             FROM {$wpdb->prefix}spiralengine_slow_query_log
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY query
             ORDER BY execution_time DESC
             LIMIT 10"
        );
        
        $stats['slow_queries'] = $slow_queries;
        
        // Generate recommendations
        $stats['recommendations'] = $this->generate_performance_recommendations($stats);
        
        return $stats;
    }
    
    /**
     * Generate performance recommendations
     */
    private function generate_performance_recommendations($stats) {
        $recommendations = array();
        
        // Page load time
        if (isset($stats['current']['page_load_time']) && $stats['current']['page_load_time'] > 3) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => __('Page load time is above 3 seconds. Consider enabling page caching.', 'spiralengine'),
                'action' => 'enable_page_cache'
            );
        }
        
        // Memory usage
        $memory_limit = $this->parse_size(ini_get('memory_limit'));
        if (isset($stats['current']['memory_peak']) && $stats['current']['memory_peak'] > $memory_limit * 0.8) {
            $recommendations[] = array(
                'type' => 'critical',
                'message' => __('Memory usage is above 80% of limit. Increase memory limit or optimize code.', 'spiralengine'),
                'action' => 'increase_memory'
            );
        }
        
        // Database queries
        if (isset($stats['current']['db_queries']) && $stats['current']['db_queries'] > 100) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => __('High number of database queries. Enable query caching.', 'spiralengine'),
                'action' => 'enable_query_cache'
            );
        }
        
        // Cache hit rate
        if (isset($stats['current']['cache_hit_rate']) && $stats['current']['cache_hit_rate'] < 80) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => __('Low cache hit rate. Review cache configuration.', 'spiralengine'),
                'action' => 'optimize_cache'
            );
        }
        
        // Slow queries
        if (!empty($stats['slow_queries'])) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => sprintf(__('%d slow queries detected. Review and optimize.', 'spiralengine'), count($stats['slow_queries'])),
                'action' => 'optimize_queries'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate cache hit rate
     */
    private function calculate_cache_hit_rate() {
        $stats = $this->cache->get_stats();
        
        if (isset($stats['hits']) && isset($stats['misses'])) {
            $total = $stats['hits'] + $stats['misses'];
            if ($total > 0) {
                return round(($stats['hits'] / $total) * 100, 2);
            }
        }
        
        return 0;
    }
    
    /**
     * Log query
     */
    public function log_query($query) {
        if (!$this->settings['monitor_queries']) {
            return $query;
        }
        
        // Record query start time
        $this->query_monitor[] = array(
            'query' => $query,
            'start_time' => microtime(true)
        );
        
        return $query;
    }
    
    /**
     * Log performance metrics
     */
    private function log_performance_metrics($metrics) {
        global $wpdb;
        
        // Calculate slow queries
        $slow_queries = array();
        foreach ($this->query_monitor as $query_data) {
            $execution_time = microtime(true) - $query_data['start_time'];
            
            if ($execution_time > $this->settings['slow_query_threshold']) {
                $slow_queries[] = array(
                    'query' => $query_data['query'],
                    'execution_time' => $execution_time
                );
            }
        }
        
        // Log to database
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_performance_log',
            array(
                'page_url' => $_SERVER['REQUEST_URI'],
                'page_load_time' => $metrics['total_time'],
                'memory_usage' => $metrics['memory_peak'],
                'db_queries' => get_num_queries(),
                'cache_hits' => $metrics['cache_hits']['hits'] ?? 0,
                'cache_misses' => $metrics['cache_hits']['misses'] ?? 0,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%f', '%d', '%d', '%d', '%d', '%s')
        );
        
        // Log slow queries
        foreach ($slow_queries as $slow_query) {
            $wpdb->insert(
                $wpdb->prefix . 'spiralengine_slow_query_log',
                array(
                    'query' => substr($slow_query['query'], 0, 500),
                    'execution_time' => $slow_query['execution_time'],
                    'page_url' => $_SERVER['REQUEST_URI'],
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%f', '%s', '%s')
            );
        }
    }
    
    /**
     * Purge cache
     */
    public function purge_cache($type = 'all') {
        switch ($type) {
            case 'all':
                $this->cache->flush();
                $this->purge_page_cache();
                $this->purge_asset_cache();
                break;
                
            case 'page':
                $this->purge_page_cache();
                break;
                
            case 'object':
                $this->cache->flush();
                break;
                
            case 'assets':
                $this->purge_asset_cache();
                break;
        }
        
        // Log cache purge
        $this->log_cache_purge($type);
    }
    
    /**
     * Purge page cache
     */
    private function purge_page_cache() {
        global $wpdb;
        
        // Delete page cache transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_spiralengine_page_%'"
        );
    }
    
    /**
     * Purge asset cache
     */
    private function purge_asset_cache() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/spiralengine-cache/';
        
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_get_performance_stats() {
        check_ajax_referer('spiralengine_performance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $stats = $this->get_performance_stats();
        wp_send_json_success($stats);
    }
    
    public function ajax_clear_cache() {
        check_ajax_referer('spiralengine_performance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'all');
        $this->purge_cache($type);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cache cleared: %s', 'spiralengine'), $type)
        ));
    }
    
    public function ajax_optimize_database() {
        check_ajax_referer('spiralengine_performance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        global $wpdb;
        
        // Optimize SPIRAL Engine tables
        $tables = $wpdb->get_col(
            "SELECT table_name 
             FROM information_schema.tables 
             WHERE table_schema = '" . DB_NAME . "' 
             AND table_name LIKE '{$wpdb->prefix}spiralengine_%'"
        );
        
        $optimized = 0;
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
            $optimized++;
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d tables optimized', 'spiralengine'), $optimized)
        ));
    }
    
    /**
     * Helper methods
     */
    private function get_current_page_load_time() {
        if (defined('SPIRALENGINE_START_TIME')) {
            return microtime(true) - SPIRALENGINE_START_TIME;
        }
        
        return 0;
    }
    
    private function parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }
    
    private function log_cache_purge($type) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_cache_log',
            array(
                'action' => 'purge',
                'cache_type' => $type,
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s')
        );
    }
}

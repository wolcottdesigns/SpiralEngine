<?php
/**
 * Plugin Name: Spiral Engine
 * Plugin URI: https://spiralengine.com
 * Description: The central hub for core system configuration, maintenance utilities, and global settings for the SPIRAL Engine platform
 * Version: 1.0.0
 * Author: SPIRAL Engine Team
 * Author URI: https://spiralengine.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: spiral-engine
 * Domain Path: /languages
 * Requires at least: 6.8.1
 * Requires PHP: 8.0
 * 
 * @package SpiralEngine
 */

// spiral-engine.php

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SPIRALENGINE_VERSION', '1.0.0');
define('SPIRALENGINE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPIRALENGINE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPIRALENGINE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SPIRALENGINE_PLUGIN_NAME', 'Spiral Engine');
define('SPIRALENGINE_TEXT_DOMAIN', 'spiral-engine');

// Include the core class file
require_once SPIRALENGINE_PLUGIN_DIR . 'includes/class-spiralengine-core.php';

/**
 * Plugin activation hook
 * This function runs when the plugin is activated
 */
function spiralengine_activate() {
    require_once SPIRALENGINE_PLUGIN_DIR . 'includes/class-spiralengine-install.php';
    SpiralEngine_Install::activate();
}
register_activation_hook(__FILE__, 'spiralengine_activate');

/**
 * Plugin deactivation hook
 * This function runs when the plugin is deactivated
 */
function spiralengine_deactivate() {
    require_once SPIRALENGINE_PLUGIN_DIR . 'includes/class-spiralengine-install.php';
    SpiralEngine_Install::deactivate();
}
register_deactivation_hook(__FILE__, 'spiralengine_deactivate');

/**
 * Initialize the plugin
 * Get the singleton instance of the core class
 */
function spiralengine_init() {
    // Load text domain for translations
    load_plugin_textdomain(
        SPIRALENGINE_TEXT_DOMAIN,
        false,
        dirname(SPIRALENGINE_PLUGIN_BASENAME) . '/languages/'
    );
    
    // Initialize the plugin core
    return SpiralEngine_Core::get_instance();
}

// Hook into plugins_loaded to ensure WordPress is fully loaded
add_action('plugins_loaded', 'spiralengine_init', 10);

/**
 * Add action links to the plugins page
 */
function spiralengine_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=spiral-engine') . '">' . 
                    __('Settings', 'spiral-engine') . '</a>';
    $docs_link = '<a href="https://spiralengine.com/docs" target="_blank">' . 
                __('Documentation', 'spiral-engine') . '</a>';
    
    array_unshift($links, $settings_link);
    $links[] = $docs_link;
    
    return $links;
}
add_filter('plugin_action_links_' . SPIRALENGINE_PLUGIN_BASENAME, 'spiralengine_plugin_action_links');

/**
 * Check for minimum requirements
 */
function spiralengine_check_requirements() {
    $errors = array();
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        $errors[] = sprintf(
            __('Spiral Engine requires PHP version 8.0 or higher. You are running version %s.', 'spiral-engine'),
            PHP_VERSION
        );
    }
    
    // Check WordPress version
    global $wp_version;
    if (version_compare($wp_version, '6.8.1', '<')) {
        $errors[] = sprintf(
            __('Spiral Engine requires WordPress version 6.8.1 or higher. You are running version %s.', 'spiral-engine'),
            $wp_version
        );
    }
    
    // Display errors if any
    if (!empty($errors)) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        deactivate_plugins(SPIRALENGINE_PLUGIN_BASENAME);
        
        wp_die(
            '<h1>' . __('Plugin Activation Error', 'spiral-engine') . '</h1>' .
            '<p>' . implode('</p><p>', $errors) . '</p>' .
            '<p><a href="' . admin_url('plugins.php') . '">' . __('Return to Plugins', 'spiral-engine') . '</a></p>'
        );
    }
}
add_action('admin_init', 'spiralengine_check_requirements');

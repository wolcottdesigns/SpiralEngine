-- SPIRAL Engine Phase 4 Database Schema
-- @package    SPIRAL_Engine
-- @subpackage Database
-- @file       database/phase4-schema.sql
-- @since      1.0.0

-- ============================================================================
-- Membership Log Table
-- Tracks all membership changes for users
-- ============================================================================
CREATE TABLE IF NOT EXISTS `{prefix}spiralengine_membership_log` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `action` varchar(50) NOT NULL COMMENT 'created, expired, upgraded, downgraded',
    `new_tier` varchar(20) NOT NULL COMMENT 'discovery, explorer, pioneer, navigator, voyager',
    `old_tier` varchar(20) DEFAULT NULL,
    `membership_id` bigint(20) unsigned DEFAULT NULL COMMENT 'MemberPress membership ID',
    `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `new_tier` (`new_tier`),
    KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Access Log Table
-- Tracks feature access changes and tier transitions
-- ============================================================================
CREATE TABLE IF NOT EXISTS `{prefix}spiralengine_access_log` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `action` varchar(50) NOT NULL,
    `old_tier` varchar(20) DEFAULT NULL,
    `new_tier` varchar(20) NOT NULL,
    `features_gained` text DEFAULT NULL COMMENT 'JSON array of feature keys',
    `features_lost` text DEFAULT NULL COMMENT 'JSON array of feature keys',
    `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Upgrade Analytics Table
-- Tracks upgrade prompt interactions and conversions
-- ============================================================================
CREATE TABLE IF NOT EXISTS `{prefix}spiralengine_upgrade_analytics` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `feature` varchar(100) NOT NULL COMMENT 'Feature or prompt identifier',
    `variant` varchar(10) NOT NULL COMMENT 'A/B test variant',
    `event_type` varchar(50) NOT NULL COMMENT 'impression, click, dismiss, conversion',
    `context` varchar(50) DEFAULT NULL COMMENT 'Where the event occurred',
    `user_tier` varchar(20) NOT NULL COMMENT 'User tier at time of event',
    `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `session_id` varchar(64) DEFAULT NULL,
    `page_url` text DEFAULT NULL,
    `referrer` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `feature_variant` (`feature`, `variant`),
    KEY `event_type` (`event_type`),
    KEY `timestamp` (`timestamp`),
    KEY `user_tier` (`user_tier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- API Rate Limiting Table
-- Tracks API usage for rate limiting
-- ============================================================================
CREATE TABLE IF NOT EXISTS `{prefix}spiralengine_api_rate_limits` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `endpoint` varchar(100) NOT NULL,
    `requests_count` int(11) NOT NULL DEFAULT 1,
    `window_start` datetime NOT NULL,
    `last_request` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_endpoint_window` (`user_id`, `endpoint`, `window_start`),
    KEY `window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Feature Gates Override Table
-- Stores dynamic feature assignments
-- ============================================================================
CREATE TABLE IF NOT EXISTS `{prefix}spiralengine_feature_overrides` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `feature` varchar(100) NOT NULL,
    `assignment_type` varchar(20) NOT NULL COMMENT 'user or role',
    `assignment_target` varchar(100) NOT NULL COMMENT 'user_id or role_name',
    `granted_by` bigint(20) unsigned NOT NULL COMMENT 'Admin who granted access',
    `expires_at` datetime DEFAULT NULL,
    `reason` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `feature_assignment` (`feature`, `assignment_type`, `assignment_target`),
    KEY `expires_at` (`expires_at`),
    KEY `assignment_type` (`assignment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Restriction Events Table
-- Tracks when users encounter restrictions
-- ============================================================================
CREATE TABLE IF NOT EXISTS `{prefix}spiralengine_restriction_events` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `feature` varchar(100) NOT NULL,
    `restriction_type` varchar(50) NOT NULL COMMENT 'blur, overlay, partial, disable',
    `required_tier` varchar(20) NOT NULL,
    `user_tier` varchar(20) NOT NULL,
    `event_type` varchar(50) NOT NULL COMMENT 'view, click, hover',
    `widget_id` varchar(100) DEFAULT NULL,
    `page_url` text DEFAULT NULL,
    `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `feature` (`feature`),
    KEY `timestamp` (`timestamp`),
    KEY `event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Membership Cache Table
-- Caches user membership calculations for performance
-- ============================================================================
CREATE TABLE IF NOT EXISTS `{prefix}spiralengine_membership_cache` (
    `user_id` bigint(20) unsigned NOT NULL,
    `tier` varchar(20) NOT NULL,
    `tier_level` tinyint(3) unsigned NOT NULL,
    `features` text DEFAULT NULL COMMENT 'JSON array of available features',
    `expiration_date` datetime DEFAULT NULL,
    `calculated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` datetime NOT NULL COMMENT 'Cache expiration',
    PRIMARY KEY (`user_id`),
    KEY `tier` (`tier`),
    KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- A/B Test Results Table
-- Stores aggregated A/B test performance data
-- ============================================================================
CREATE TABLE IF NOT EXISTS `{prefix}spiralengine_ab_test_results` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `feature` varchar(100) NOT NULL,
    `variant` varchar(10) NOT NULL,
    `date` date NOT NULL,
    `impressions` int(11) NOT NULL DEFAULT 0,
    `clicks` int(11) NOT NULL DEFAULT 0,
    `conversions` int(11) NOT NULL DEFAULT 0,
    `revenue` decimal(10,2) DEFAULT 0.00,
    `unique_users` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `feature_variant_date` (`feature`, `variant`, `date`),
    KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- User Storage Usage Table
-- Tracks storage usage per user for limit enforcement
-- ============================================================================
CREATE TABLE IF NOT EXISTS `{prefix}spiralengine_user_storage` (
    `user_id` bigint(20) unsigned NOT NULL,
    `episode_count` int(11) NOT NULL DEFAULT 0,
    `file_count` int(11) NOT NULL DEFAULT 0,
    `total_size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
    `last_episode_date` datetime DEFAULT NULL,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    KEY `updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- AI Token Usage Table
-- Tracks AI token consumption per user
-- ============================================================================
CREATE TABLE IF NOT EXISTS `{prefix}spiralengine_ai_usage` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `feature` varchar(100) NOT NULL COMMENT 'Which AI feature was used',
    `model` varchar(50) NOT NULL COMMENT 'AI model used',
    `tokens_used` int(11) NOT NULL,
    `cost` decimal(10,6) DEFAULT NULL COMMENT 'Cost in USD',
    `month` char(7) NOT NULL COMMENT 'YYYY-MM format',
    `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_month` (`user_id`, `month`),
    KEY `feature` (`feature`),
    KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Indexes for Performance
-- ============================================================================

-- Add composite indexes for common queries
ALTER TABLE `{prefix}spiralengine_upgrade_analytics`
ADD INDEX `conversion_tracking` (`user_id`, `event_type`, `timestamp`);

ALTER TABLE `{prefix}spiralengine_restriction_events`
ADD INDEX `user_feature_events` (`user_id`, `feature`, `event_type`);

-- ============================================================================
-- Views for Reporting
-- ============================================================================

-- User tier distribution view
CREATE OR REPLACE VIEW `{prefix}spiralengine_tier_distribution` AS
SELECT 
    tier,
    COUNT(*) as user_count,
    AVG(DATEDIFF(NOW(), calculated_at)) as avg_days_in_tier
FROM `{prefix}spiralengine_membership_cache`
WHERE expires_at > NOW()
GROUP BY tier;

-- Conversion funnel view
CREATE OR REPLACE VIEW `{prefix}spiralengine_conversion_funnel` AS
SELECT 
    feature,
    variant,
    COUNT(DISTINCT CASE WHEN event_type = 'impression' THEN user_id END) as users_saw,
    COUNT(DISTINCT CASE WHEN event_type = 'click' THEN user_id END) as users_clicked,
    COUNT(DISTINCT CASE WHEN event_type = 'conversion' THEN user_id END) as users_converted,
    ROUND(COUNT(DISTINCT CASE WHEN event_type = 'click' THEN user_id END) * 100.0 / 
          NULLIF(COUNT(DISTINCT CASE WHEN event_type = 'impression' THEN user_id END), 0), 2) as ctr,
    ROUND(COUNT(DISTINCT CASE WHEN event_type = 'conversion' THEN user_id END) * 100.0 / 
          NULLIF(COUNT(DISTINCT CASE WHEN event_type = 'click' THEN user_id END), 0), 2) as conversion_rate
FROM `{prefix}spiralengine_upgrade_analytics`
WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY feature, variant;

-- ============================================================================
-- Stored Procedures
-- ============================================================================

DELIMITER $$

-- Procedure to clean up expired data
CREATE PROCEDURE IF NOT EXISTS `{prefix}spiralengine_cleanup_expired_data`()
BEGIN
    -- Delete expired cache entries
    DELETE FROM `{prefix}spiralengine_membership_cache` 
    WHERE expires_at < NOW();
    
    -- Delete expired feature overrides
    DELETE FROM `{prefix}spiralengine_feature_overrides` 
    WHERE expires_at IS NOT NULL AND expires_at < NOW();
    
    -- Delete old rate limit entries (older than 1 hour)
    DELETE FROM `{prefix}spiralengine_api_rate_limits` 
    WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR);
    
    -- Archive old analytics data (older than 90 days)
    DELETE FROM `{prefix}spiralengine_upgrade_analytics` 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    DELETE FROM `{prefix}spiralengine_restriction_events` 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
END$$

-- Procedure to calculate user storage usage
CREATE PROCEDURE IF NOT EXISTS `{prefix}spiralengine_calculate_user_storage`(IN p_user_id BIGINT)
BEGIN
    DECLARE v_episode_count INT;
    DECLARE v_file_count INT;
    DECLARE v_total_size BIGINT;
    
    -- Get episode count (would come from episodes table)
    SELECT COUNT(*) INTO v_episode_count
    FROM `{prefix}spiralengine_episodes`
    WHERE user_id = p_user_id;
    
    -- Update storage table
    INSERT INTO `{prefix}spiralengine_user_storage` 
        (user_id, episode_count, file_count, total_size_bytes)
    VALUES 
        (p_user_id, v_episode_count, 0, 0)
    ON DUPLICATE KEY UPDATE
        episode_count = v_episode_count,
        updated_at = NOW();
END$$

DELIMITER ;

-- ============================================================================
-- Triggers
-- ============================================================================

DELIMITER $$

-- Trigger to update user storage on episode insert
CREATE TRIGGER IF NOT EXISTS `{prefix}spiralengine_after_episode_insert`
AFTER INSERT ON `{prefix}spiralengine_episodes`
FOR EACH ROW
BEGIN
    CALL `{prefix}spiralengine_calculate_user_storage`(NEW.user_id);
END$$

-- Trigger to clean up user data on deletion
CREATE TRIGGER IF NOT EXISTS `{prefix}spiralengine_before_user_delete`
BEFORE DELETE ON `{prefix}users`
FOR EACH ROW
BEGIN
    DELETE FROM `{prefix}spiralengine_membership_cache` WHERE user_id = OLD.ID;
    DELETE FROM `{prefix}spiralengine_user_storage` WHERE user_id = OLD.ID;
    DELETE FROM `{prefix}spiralengine_feature_overrides` 
    WHERE assignment_type = 'user' AND assignment_target = OLD.ID;
END$$

DELIMITER ;

-- ============================================================================
-- Initial Data
-- ============================================================================

-- Insert default tier features (managed by PHP, but useful for reference)
INSERT IGNORE INTO `{prefix}options` (option_name, option_value, autoload) VALUES
('spiralengine_tier_features', 'a:5:{s:9:"discovery";a:3:{s:13:"basic_logging";b:1;s:16:"dashboard_access";b:1;s:12:"quick_logger";b:1;}s:8:"explorer";a:7:{s:13:"basic_logging";b:1;s:16:"dashboard_access";b:1;s:12:"quick_logger";b:1;s:17:"pattern_detection";b:1;s:18:"basic_correlations";b:1;s:13:"timeline_view";b:1;s:15:"severity_trends";b:1;}s:7:"pioneer";a:11:{s:13:"basic_logging";b:1;s:16:"dashboard_access";b:1;s:12:"quick_logger";b:1;s:17:"pattern_detection";b:1;s:18:"basic_correlations";b:1;s:13:"timeline_view";b:1;s:15:"severity_trends";b:1;s:18:"advanced_analytics";b:1;s:16:"trigger_analysis";b:1;s:20:"personalized_insights";b:1;s:19:"biological_tracking";b:1;}s:9:"navigator";a:16:{s:13:"basic_logging";b:1;s:16:"dashboard_access";b:1;s:12:"quick_logger";b:1;s:17:"pattern_detection";b:1;s:18:"basic_correlations";b:1;s:13:"timeline_view";b:1;s:15:"severity_trends";b:1;s:18:"advanced_analytics";b:1;s:16:"trigger_analysis";b:1;s:20:"personalized_insights";b:1;s:19:"biological_tracking";b:1;s:11:"ai_forecast";b:1;s:17:"unified_forecast";b:1;s:11:"export_data";b:1;s:16:"advanced_reports";b:1;s:16:"caregiver_access";b:1;}s:7:"voyager";a:21:{s:13:"basic_logging";b:1;s:16:"dashboard_access";b:1;s:12:"quick_logger";b:1;s:17:"pattern_detection";b:1;s:18:"basic_correlations";b:1;s:13:"timeline_view";b:1;s:15:"severity_trends";b:1;s:18:"advanced_analytics";b:1;s:16:"trigger_analysis";b:1;s:20:"personalized_insights";b:1;s:19:"biological_tracking";b:1;s:11:"ai_forecast";b:1;s:17:"unified_forecast";b:1;s:11:"export_data";b:1;s:16:"advanced_reports";b:1;s:16:"caregiver_access";b:1;s:10:"api_access";b:1;s:14:"custom_widgets";b:1;s:11:"white_label";b:1;s:15:"bulk_operations";b:1;s:16:"priority_support";b:1;}}', 'yes');

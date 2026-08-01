<?php

/**
 * Fired when the plugin is deleted.
 *
 * This file is used to clean up the database tables and options
 * created by the plugin.
 *
 * @package    LearnDashAIGrader
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * 1. Drop Custom Tables
 */
$table_logs = $wpdb->prefix . 'ld_ai_logs';
$table_feedback = $wpdb->prefix . 'ld_ai_student_feedback';

$wpdb->query( "DROP TABLE IF EXISTS $table_logs" );
$wpdb->query( "DROP TABLE IF EXISTS $table_feedback" );

/**
 * 2. Delete Options / Settings
 */
delete_option( 'ld_ai_grader_settings' );
delete_option( 'ld_ai_grader_db_version' );

// Clear any scheduled actions related to our plugin
if ( class_exists( 'ActionScheduler_Store' ) ) {
    try {
        ActionScheduler_Store::instance()->cancel_actions_by_group( 'learndash-ai-grader' );
    } catch ( Exception $e ) {
        // Log error or ignore if Action Scheduler is not present
    }
}
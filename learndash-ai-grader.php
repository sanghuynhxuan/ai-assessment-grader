<?php
/**
 * Plugin Name:       LearnDash AI Grader & Feedback
 * Plugin URI:        https://example.test/learndash-ai-grader
 * Description:       Integrates OpenAI, Anthropic, Gemini into LearnDash for intelligent grading and student feedback.
 * Version:           3.0.0
 * Author:            Sang Huynh Xuan
 * Author URI:        https://example.test
 * Text Domain:       learndash-ai-grader
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL v3 or later
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define Plugin Constants
define( 'LD_AI_VERSION', '2.1.0' );
define( 'LD_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LD_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LD_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load Composer Autoloader
 */
if ( file_exists( LD_AI_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once LD_AI_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Initialize Action Scheduler (if loading via Composer)
 */
if ( file_exists( LD_AI_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
    require_once LD_AI_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

/**
 * Activation Hook
 * Creates custom DB tables and schedules cron jobs.
 */
function activate_learndash_ai_grader() {
	require_once LD_AI_PLUGIN_DIR . 'includes/Core/Activator.php';
	LearnDashAIGrader\Core\Activator::activate();
}

/**
 * Deactivation Hook
 * Clears scheduled cron jobs.
 */
function deactivate_learndash_ai_grader() {
	require_once LD_AI_PLUGIN_DIR . 'includes/Core/Deactivator.php';
	LearnDashAIGrader\Core\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_learndash_ai_grader' );
register_deactivation_hook( __FILE__, 'deactivate_learndash_ai_grader' );

/**
 * Core Plugin Execution
 * Fires when all plugins are loaded.
 */
function run_learndash_ai_grader() {
    // Check if LearnDash is active before running
    if ( ! class_exists( 'SFWD_LMS' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'LearnDash AI Grader requires LearnDash LMS to be installed and active.', 'learndash-ai-grader' ) . '</p></div>';
        });
        return;
    }

	// Initialize the Main Plugin Class
    // Note: We assume includes/Core/Plugin.php exists (we will create it next)
    if ( class_exists( 'LearnDashAIGrader\Core\Plugin' ) ) {
        $plugin = new LearnDashAIGrader\Core\Plugin();
        $plugin->run();
    }
}
add_action( 'plugins_loaded', 'run_learndash_ai_grader' );

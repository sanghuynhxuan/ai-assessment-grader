<?php

namespace LearnDashAIGrader\Admin;

class LogsPage {

    public function render_page() {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'ld_ai_logs';
        $table_feedback = $wpdb->prefix . 'ld_ai_student_feedback'; // Define Feedback Table

        // 1. Auto-cleanup logic (Delete logs older than 7 days)
        // Also clean orphaned feedback
        $wpdb->query( 
            $wpdb->prepare( 
                "DELETE FROM $table_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", 
                7 
            ) 
        );
        // Clean feedback that has no parent log
        $wpdb->query( "DELETE FROM $table_feedback WHERE log_id NOT IN (SELECT id FROM $table_logs)" );

        // 2. Manual Clear All Logic
        if ( isset( $_POST['ld_ai_clear_logs'] ) && check_admin_referer( 'ld_ai_clear_logs_nonce' ) ) {
            // TRUNCATE BOTH TABLES
            $wpdb->query( "TRUNCATE TABLE $table_logs" );
            $wpdb->query( "TRUNCATE TABLE $table_feedback" );
            
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'All logs and ratings have been cleared.', 'learndash-ai-grader' ) . '</p></div>';
        }

        // 3. Fetch Data (Limit 50)
        $logs = $wpdb->get_results( "SELECT * FROM $table_logs ORDER BY id DESC LIMIT 50" );

        // 4. Load Template
        require_once LD_AI_PLUGIN_DIR . 'templates/admin/logs-page.php';
    }
}
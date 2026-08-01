<?php

namespace LearnDashAIGrader\Api;

class AdminController {

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'ld-ai-grader/v1', '/admin/stats', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_stats' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ));
    }

    public function get_stats() {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'ld_ai_logs';
        $table_feedback = $wpdb->prefix . 'ld_ai_student_feedback';

        $total_graded = $wpdb->get_var( "SELECT COUNT(id) FROM $table_logs WHERE status = 'completed'" );
        $total_cost   = $wpdb->get_var( "SELECT SUM(cost_estimated) FROM $table_logs" );
        $avg_score    = $wpdb->get_var( "SELECT AVG(ai_score) FROM $table_logs WHERE status = 'completed'" );

        $dates = [];
        $counts = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(id) FROM $table_logs WHERE DATE(created_at) = %s", $date
            ));
            $dates[] = date('d/m', strtotime($date));
            $counts[] = (int)$count;
        }

        $providers = $wpdb->get_results( "SELECT provider, COUNT(id) as count FROM $table_logs GROUP BY provider" );

        $logs = $wpdb->get_results( "SELECT id, provider, model, ai_score, cost_estimated, created_at FROM $table_logs ORDER BY id DESC LIMIT 10" );

        return new \WP_REST_Response([
            'summary' => [
                'total_graded' => (int)$total_graded,
                'total_cost'   => round((float)$total_cost, 4),
                'avg_score'    => round((float)$avg_score, 1)
            ],
            'chart_daily' => [
                'labels' => $dates,
                'data'   => $counts
            ],
            'chart_provider' => $providers,
            'recent_logs' => $logs
        ], 200);
    }
}
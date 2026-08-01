<?php

namespace LearnDashAIGrader\Api;

use LearnDashAIGrader\Providers\ProviderFactory;

class TestingController {

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'ld-ai-grader/v1', '/test/run', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'run_test' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' ); // Chỉ admin mới được test
            },
        ));
    }

    public function run_test( $request ) {
        global $wpdb;
        
        $provider_slug = $request->get_param( 'provider' );
        $model         = $request->get_param( 'model' );
        $question      = $request->get_param( 'question' );
        $rubric        = $request->get_param( 'rubric' );
        $student_ans   = $request->get_param( 'student_answer' );

        try {
            $provider = ProviderFactory::get_provider( $provider_slug );
            
            $result = $provider->grade( $question, $student_ans, $rubric, [ 'model' => $model ] );

            $cost = $result['tokens'] * 0.00001; 

            $table_logs = $wpdb->prefix . 'ld_ai_logs';
            $wpdb->insert(
                $table_logs,
                [
                    'user_id' => get_current_user_id(),
                    'provider' => $provider_slug,
                    'model' => $model,
                    'input_prompt' => "Question: $question | Rubric: $rubric",
                    'student_response' => $student_ans,
                    'ai_response_raw' => $result['raw_json'],
                    'ai_feedback_text' => $result['feedback'],
                    'ai_score' => $result['score'],
                    'tokens_used' => $result['tokens'],
                    'cost_estimated' => $cost,
                    'status' => 'completed',
                    'is_test_mode' => 1 
                ]
            );

            return new \WP_REST_Response([
                'success'  => true,
                'score'    => $result['score'],
                'feedback' => $result['feedback'],
                'tokens'   => $result['tokens'],
                'cost'     => number_format($cost, 6),
                'raw'      => $result['raw_json']
            ], 200);

        } catch ( \Exception $e ) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
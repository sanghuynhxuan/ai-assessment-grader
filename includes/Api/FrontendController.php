<?php

namespace LearnDashAIGrader\Api;

class FrontendController {

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_filter( 'the_content', array( $this, 'inject_feedback_into_content' ) );
        add_filter( 'learndash_quiz_question_answer_post_content', array( $this, 'inject_feedback_into_quiz_result' ), 20, 5 );
    }

    public function get_grading_result( $request ) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $question_id = $request->get_param( 'question_id' ); 

    if ( empty($user_id) ) return new \WP_REST_Response(['status' => 'pending', 'msg' => 'Wait Auth'], 200);

    $table_logs = $wpdb->prefix . 'ld_ai_logs';

    $log = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table_logs 
         WHERE user_id = %d AND question_id = %d 
         ORDER BY id DESC LIMIT 1",
        $user_id, $question_id
    ));

    if ( ! $log ) return new \WP_REST_Response( ['status' => 'not_found'], 200 );

    $service = new \LearnDashAIGrader\Engine\GradingService();
    $real_max_points = $service->get_question_max_points( $question_id );
    if ( $real_max_points <= 0 ) $real_max_points = 1;

    $earned_points = round( ($log->ai_score / 100) * $real_max_points, 2 );

    return new \WP_REST_Response( [
        'status'   => 'completed',
        'log_id'   => $log->id,
        'score'    => $earned_points,
        'max_points' => $real_max_points, 
        'percentage' => $log->ai_score,
        'feedback' => wpautop( $log->ai_feedback_text )
    ], 200 );
}

    public function register_routes() { 
        register_rest_route('ld-ai-grader/v1','/result', ['methods'=>'GET','callback'=>[$this,'get_grading_result'],'permission_callback'=>'__return_true']); 
        register_rest_route('ld-ai-grader/v1','/rate', ['methods'=>'POST','callback'=>[$this,'save_student_feedback'],'permission_callback'=>'__return_true']); 
    }
    public function check_permission() { return true; }
    public function save_student_feedback($r) { global $wpdb; $tbl=$wpdb->prefix.'ld_ai_student_feedback'; $wpdb->insert($tbl, ['log_id'=>$r['log_id'], 'user_id'=>get_current_user_id(), 'rating'=>$r['rating']]); return new \WP_REST_Response(['success'=>true],200); }
    public function inject_feedback_into_quiz_result($content, $qid, $quiz_id, $uid, $post) { return $content; } 
    public function inject_feedback_into_content($content) { return $content; }
}
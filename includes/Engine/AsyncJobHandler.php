<?php

namespace LearnDashAIGrader\Engine;

class AsyncJobHandler {

    public static $HOOK_NAME = 'ld_ai_grade_single_question';

    public function init() {
        add_action( self::$HOOK_NAME, array( $this, 'handle_grading_job' ), 10, 1 );
    }

    public function handle_grading_job( $args ) {
        if ( empty( $args['question_id'] ) || empty( $args['response_text'] ) ) {
            return;
        }

        $service = new GradingService();
        $service->grade_submission( $args );
    }
}
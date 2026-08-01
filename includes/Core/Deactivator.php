<?php

namespace LearnDashAIGrader\Core;

class Deactivator {

	public static function deactivate() {
        if ( class_exists( 'ActionScheduler_Store' ) ) {
            try {
                \ActionScheduler_Store::instance()->cancel_actions_by_group( 'learndash-ai-grader' );
            } catch ( \Exception $e ) {
            }
        }
	}
}
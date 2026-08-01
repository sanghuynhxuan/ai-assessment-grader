<?php

namespace LearnDashAIGrader\Core;


class Activator {

	public static function activate() {
		self::create_tables();
	}

	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_logs = $wpdb->prefix . 'ld_ai_logs';
		$sql_logs = "CREATE TABLE $table_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL DEFAULT 0,
			quiz_id bigint(20) NOT NULL DEFAULT 0,
			question_id bigint(20) NOT NULL DEFAULT 0,
            statistic_ref_id bigint(20) DEFAULT 0, 
            provider varchar(50) NOT NULL,
            model varchar(100) NOT NULL,
			input_prompt longtext,
			student_response longtext,
			ai_response_raw longtext,
            ai_feedback_text longtext,
            ai_score float DEFAULT 0,
            tokens_used int(11) DEFAULT 0,
            cost_estimated decimal(10,6) DEFAULT 0.000000,
            status varchar(20) DEFAULT 'pending',
            is_test_mode tinyint(1) DEFAULT 0,
            error_message text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id),
            KEY status (status)
		) $charset_collate;";

        $table_feedback = $wpdb->prefix . 'ld_ai_student_feedback';
        $sql_feedback = "CREATE TABLE $table_feedback (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            rating tinyint(1) NOT NULL, 
            comment text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY log_id (log_id)
        ) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_logs );
        dbDelta( $sql_feedback );
	}
}
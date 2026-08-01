<?php

namespace LearnDashAIGrader\Integrations;

use LearnDashAIGrader\Engine\GradingService;

class LearnDashQuizHooks
{
    public function init()
    {
        add_action(
            "learndash_essay_submitted",
            [$this, "trigger_grading_standard"],
            10,
            3
        );
        add_action(
            "save_post_sfwd-essays",
            [$this, "trigger_grading_save_post"],
            10,
            3
        );

        add_action(
            "wp_pro_quiz_completed_quiz",
            [$this, "finalize_db_update"],
            10,
            1
        );

        add_action("init", [$this, "intercept_quiz_submission_universal"], 10);
    }

    public function trigger_grading_standard(
        $essay_id,
        $essay_args,
        $essay_post
    ) {
        $this->process_grading(
            $essay_id,
            "essay",
            $essay_args["question_post_id"] ?? 0,
            $essay_args["quiz_post_id"] ?? 0
        );
    }
    public function trigger_grading_save_post($post_id, $post, $update)
    {
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }
        if (
            !in_array($post->post_status, [
                "publish",
                "graded",
                "not_graded",
                "pending",
            ])
        ) {
            return;
        }
        if ($post->post_type !== "sfwd-essays") {
            return;
        }
        $this->process_grading($post_id, "essay_save");
    }
    public function intercept_ajax_submission()
    {
        $this->intercept_quiz_submission_universal();
    }

    public function intercept_quiz_submission_universal()
    {
        if (empty($_POST)) {
            return;
        }
        $raw_data = [];
        $quiz_id = 0;
        try {
            if (!empty($_POST["results"])) {
                $json = stripslashes($_POST["results"]);
                $raw_data = json_decode($json, true);
                if (isset($_POST["quiz"])) {
                    $quiz_id = intval($_POST["quiz"]);
                }
            } elseif (!empty($_POST["data"]["responses"])) {
                $responses = $_POST["data"]["responses"];
                if (is_string($responses)) {
                    $json = stripslashes($responses);
                    $raw_data = json_decode($json, true);
                } elseif (is_array($responses)) {
                    $raw_data = $responses;
                }
                if (isset($_POST["data"]["quiz"])) {
                    $quiz_id = intval($_POST["data"]["quiz"]);
                }
            }
        } catch (\Exception $e) {
            return;
        }

        if (empty($raw_data) || !is_array($raw_data)) {
            return;
        }
        $user_id = get_current_user_id();

        foreach ($raw_data as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $pro_id = 0;
            $resp_val = "";
            if (isset($item["question_pro_id"])) {
                $pro_id = intval($item["question_pro_id"]);
                $resp_val = $item["response"] ?? "";
            } elseif (isset($item["data"])) {
                $pro_id = intval($key);
                $resp_val = $item["data"];
            }
            if (!$pro_id) {
                continue;
            }

            global $wpdb;
            $q_type = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT answer_type FROM {$wpdb->prefix}learndash_pro_quiz_question WHERE id = %d",
                    $pro_id
                )
            );

            $student_text = "";
            if (is_array($resp_val)) {
                $clean = $resp_val;
                $student_text = implode(" | ", $clean);
            } else {
                $student_text = (string) $resp_val;
            }
            $clean_text = trim(strip_tags($student_text));

            if ($q_type === "essay") {
                if (!empty($clean_text)) {
                    continue;
                }
            }

            $post_id = $this->get_post_id_from_pro_id($pro_id);

            if (in_array($q_type, ["free_answer", "cloze_answer", "essay"])) {
                $this->process_grading(
                    0,
                    $q_type,
                    $post_id,
                    $quiz_id,
                    $student_text,
                    $user_id,
                    0
                );
            }
        }
    }

    public function finalize_db_update( $mapper ) {
    global $wpdb;
    
    $statistic_ref_id = 0;
    $user_id = get_current_user_id();

    if ( is_object( $mapper ) ) {
        if ( method_exists( $mapper, 'getStatisticRefId' ) ) {
            $statistic_ref_id = $mapper->getStatisticRefId();
        }
        if ( method_exists( $mapper, 'getUserId' ) ) {
            $user_id = $mapper->getUserId();
        }
    } elseif ( is_numeric( $mapper ) && $mapper > 0 ) {
        $statistic_ref_id = intval($mapper);
    }

    if ( empty( $statistic_ref_id ) || $statistic_ref_id == 0 ) {
        $table_ref = $wpdb->prefix . 'learndash_pro_quiz_statistic_ref';
        $found_ref = $wpdb->get_row( $wpdb->prepare(
            "SELECT statistic_ref_id, create_time FROM $table_ref 
             WHERE user_id = %d 
             ORDER BY statistic_ref_id DESC LIMIT 1",
            $user_id
        ));

        if ( $found_ref ) {
            $time_diff = time() - intval($found_ref->create_time);
            if ( $time_diff < 120 ) {
                $statistic_ref_id = $found_ref->statistic_ref_id;
            } else {
                return;
            }
        } else {
            return;
        }
    }

    $table_stat = $wpdb->prefix . 'learndash_pro_quiz_statistic';
    $table_logs = $wpdb->prefix . 'ld_ai_logs';
    
    $results = $wpdb->get_results( $wpdb->prepare("SELECT question_id, points FROM $table_stat WHERE statistic_ref_id = %d", $statistic_ref_id));

    if(empty($results)) return;

    $service = new \LearnDashAIGrader\Engine\GradingService();
    $has_updates = false;

    foreach ( $results as $row ) {
        $pro_id = $row->question_id; 
        $post_id = $this->get_post_id_from_pro_id( $pro_id );
        
        $log = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, ai_score, created_at FROM $table_logs 
             WHERE user_id = %d AND question_id = %d 
             ORDER BY id DESC LIMIT 1",
            $user_id, $post_id
        ));

        if ( $log ) {
            $log_time = strtotime( $log->created_at );
            $time_diff = time() - $log_time;

            if ( $time_diff > 30 ) { 
                continue;
            }

            $max_points = $service->get_real_max_points($pro_id);
            if ($max_points <= 0) $max_points = 1;

            $ai_points = round( ($log->ai_score / 100) * $max_points, 2 );
            $is_correct = ($ai_points > 0.1) ? 1 : 0;
            
            $wpdb->update(
                $table_stat, 
                [ 
                    'points' => $ai_points, 
                    'correct_count' => $is_correct, 
                    'incorrect_count' => (1 - $is_correct) 
                ], 
                [ 
                    'statistic_ref_id' => $statistic_ref_id, 
                    'question_id' => $pro_id 
                ]
            );

            $wpdb->update($table_logs, ['statistic_ref_id' => $statistic_ref_id], ['id' => $log->id]);
            $has_updates = true;
        }
    }

    if ( $has_updates ) {
        $service->recalculate_total_quiz_score($statistic_ref_id);
    }
}
    private function get_post_id_from_pro_id($pro_id)
    {
        global $wpdb;
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='question_pro_id' AND meta_value=%d LIMIT 1",
                $pro_id
            )
        );
        return $id ? intval($id) : 0;
    }
    private function process_grading(
        $essay_id = 0,
        $source = "",
        $input_qid = 0,
        $input_quiz_id = 0,
        $response_text_in = "",
        $user_id = 0,
        $stat_ref_id = 0
    ) {
        $response_text = $response_text_in;
        if ($essay_id) {
            static $processed = [];
            if (isset($processed[$essay_id])) {
                return;
            }
            $processed[$essay_id] = true;
            $post = get_post($essay_id);
            if (!$post) {
                return;
            }
            $quiz_id =
                $input_quiz_id ?:
                get_post_meta($essay_id, "quiz_post_id", true);
            $question_id =
                $input_qid ?:
                get_post_meta($essay_id, "question_post_id", true);
            $response_text = $post->post_content;
            $user_id = $post->post_author;
        } else {
            $quiz_id = $input_quiz_id;
            $question_id = $input_qid;
        }
        $question_id = intval($question_id);
        if (empty($question_id)) {
            return;
        }
        $lock_key =
            "ld_ai_lock_" . $user_id . "_" . $quiz_id . "_" . $question_id;
        if (get_transient($lock_key)) {
            return;
        }
        set_transient($lock_key, true, 5);
        try {
            $service = new GradingService();
            $service->grade_submission([
                "user_id" => $user_id,
                "quiz_id" => $quiz_id,
                "question_id" => $question_id,
                "response_text" => $response_text,
                "essay_post_id" => $essay_id,
                "statistic_ref_id" => $stat_ref_id,
            ]);
        } catch (\Exception $e) {
        }
    }
}

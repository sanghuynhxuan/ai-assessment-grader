<?php

namespace LearnDashAIGrader\Engine;

use LearnDashAIGrader\Providers\ProviderFactory;

class GradingService {

    private function log($msg) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[LD AI DEBUG] ' . $msg);
        }
    }

    public function grade_submission( $args ) {
    global $wpdb;

    $user_id = intval($args['user_id']);
    $question_post_id = intval($args['question_id']);
    $quiz_post_id = intval($args['quiz_id']);
    $essay_id = intval($args['essay_post_id']);
    $stat_ref_id = isset($args['statistic_ref_id']) ? intval($args['statistic_ref_id']) : 0;
    $raw_response = $args['response_text'] ?? '';

    $recent_log = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, created_at, student_response FROM {$wpdb->prefix}ld_ai_logs 
         WHERE user_id = %d AND question_id = %d 
         ORDER BY id DESC LIMIT 1",
        $user_id, $question_post_id
    ));

    if ( $recent_log ) {
        $old_response = trim($recent_log->student_response);
        $new_response = trim($raw_response);

        if ( $old_response === $new_response ) {
            $log_utc_timestamp = strtotime( get_gmt_from_date( $recent_log->created_at ) );
            $time_diff = time() - $log_utc_timestamp;

            if ( $time_diff >= 0 && $time_diff < 5 ) {
                return;
            }
        }
    }

    $clean_response = trim(strip_tags($raw_response));
    $max_points = $this->get_question_max_points( $question_post_id );
    if ($max_points <= 0) $max_points = 1;

    $settings = get_option( 'ld_ai_grader_settings' );
    
    $model_selection = get_post_meta( $question_post_id, '_ld_ai_model_override', true );
    if ( empty($model_selection) ) {
        $model_selection = $settings['active_model'] ?? 'openai:gpt-4o';
    }

    $parts = explode(':', $model_selection);
    if ( count($parts) < 2 ) { 
        $provider_slug = 'openai'; 
        $model_name = $model_selection; 
    } else {
        $provider_slug = $parts[0];
        $model_name = $parts[1];
    }

    if ( empty($clean_response) ) {
        $ai_result = [ 'score' => 0, 'feedback' => 'No answer provided.', 'raw_json' => '{"score":0}', 'tokens' => 0 ];
        $provider_name = 'System';
    } else {
        $custom_prompt = get_post_meta( $question_post_id, '_ld_ai_custom_prompt', true );
        if ( empty( $custom_prompt ) ) $custom_prompt = $settings['global_prompt_essay'] ?? '';
        $custom_prompt .= " Output valid JSON: {\"score\": 0-100, \"feedback\": \"...\"}";

        try {
            $provider = \LearnDashAIGrader\Providers\ProviderFactory::get_provider( $provider_slug );
            $provider_name = $provider->get_name();
            
            $ai_result = $provider->grade( 
                get_the_title($question_post_id), 
                $clean_response, 
                get_post_field('post_content', $question_post_id), 
                [ 'model' => $model_name, 'system_prompt' => $custom_prompt ] 
            );
        } catch ( \Exception $e ) { 
            return; 
        }
    }

    $tokens_used = isset($ai_result['tokens']) ? intval($ai_result['tokens']) : 0;
    $price_per_token = 0.000001;

    if ( $provider_slug === 'openai' ) {
        if ( strpos($model_name, 'gpt-4o-mini') !== false ) {
            $price_per_token = 0.0000003;
        } elseif ( strpos($model_name, 'gpt-4o') !== false ) {
            $price_per_token = 0.000005;
        } elseif ( strpos($model_name, 'gpt-4-turbo') !== false ) {
            $price_per_token = 0.00002;
        } elseif ( strpos($model_name, 'gpt-3.5') !== false ) {
            $price_per_token = 0.000001;
        }
    } elseif ( $provider_slug === 'anthropic' ) {
        if ( strpos($model_name, 'sonnet') !== false ) {
            $price_per_token = 0.000006;
        } elseif ( strpos($model_name, 'opus') !== false ) {
            $price_per_token = 0.00003;
        } elseif ( strpos($model_name, 'haiku') !== false ) {
            $price_per_token = 0.0000005;
        }
    } elseif ( $provider_slug === 'gemini' ) {
        if ( strpos($model_name, 'pro') !== false ) {
            $price_per_token = 0.000007;
        } elseif ( strpos($model_name, 'flash') !== false ) {
            $price_per_token = 0.0000007;
        }
    }

    $cost_estimated = number_format($tokens_used * $price_per_token, 8, '.', '');

    $ai_score_raw = floatval($ai_result['score']);
    if ($ai_score_raw > 100) $ai_score_raw = 100;
    if ($ai_score_raw < 0) $ai_score_raw = 0;

    $wpdb->insert( $wpdb->prefix . 'ld_ai_logs', [
        'user_id' => $user_id, 
        'quiz_id' => $quiz_post_id, 
        'question_id' => $question_post_id,
        'statistic_ref_id' => $stat_ref_id,
        'provider' => $provider_name, 
        'model' => $model_name,
        'input_prompt' => get_the_title($question_post_id),
        'student_response' => $raw_response, 
        'ai_response_raw' => $ai_result['raw_json'] ?? '',
        'ai_feedback_text' => $ai_result['feedback'], 
        'ai_score' => $ai_score_raw, 
        'tokens_used' => $tokens_used,
        'cost_estimated' => $cost_estimated, 
        'status' => 'completed', 
        'created_at' => current_time('mysql')
    ]);
    
    $log_id = $wpdb->insert_id;

    if ( function_exists('wp_cache_delete') ) {
        wp_cache_flush(); 
    }

    $final_points = round( ($ai_score_raw / 100) * $max_points, 2 );

    if ( $essay_id > 0 ) {
        update_post_meta( $essay_id, 'ai_feedback_generated', $ai_result['feedback'] );
        $this->update_essay_api_debug($essay_id, $final_points);
        if ( $stat_ref_id > 0 ) {
            $this->update_activity_graded_meta( $stat_ref_id, $essay_id, $final_points );
        }
    }

    if ( $stat_ref_id > 0 ) {
        sleep(1);
        $this->update_statistic_db($stat_ref_id, $question_post_id, $final_points, $quiz_post_id, $user_id);
    }
}

    private function update_essay_api_debug($id, $points) { 
        $this->log("API Call for Essay $id, Points $points");
        $post = get_post($id);
        if ($post && get_current_user_id() == 0) wp_set_current_user($post->post_author);

        $req = new \WP_REST_Request('POST', '/ldlms/v2/sfwd-essays/' . $id);
        $req->set_header('Content-Type', 'application/json');
        $req->set_body_params([ 'status' => 'graded', 'points_awarded' => $points ]);
        
        $response = rest_do_request($req);
        if ($response->is_error()) $this->log("API Error: " . $response->get_error_message());
        else $this->log("API Success");
    }

    public function get_question_max_points( $question_post_id ) {
        $meta = get_post_meta( $question_post_id, 'question_points', true );
        if ( ! empty($meta) && is_numeric($meta) ) return floatval($meta);
        $sfwd = get_post_meta( $question_post_id, '_sfwd-question', true );
        if ( isset($sfwd['sfwd-question_question_points']) ) return floatval($sfwd['sfwd-question_question_points']);
        global $wpdb;
        $sql_points = $wpdb->get_var( $wpdb->prepare("SELECT points FROM {$wpdb->prefix}learndash_pro_quiz_question WHERE id = (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'question_pro_id')", $question_post_id) );
        return ($sql_points) ? floatval($sql_points) : 10;
    }

    public function get_real_max_points($pro_id) {
        global $wpdb;
        $pid = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='question_pro_id' AND meta_value=%d", $pro_id));
        return $this->get_question_max_points($pid);
    }

    public function update_activity_graded_meta( $ref_id, $essay_post_id, $points ) {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'learndash_user_activity_meta';

        $activity_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT activity_id FROM $meta_table WHERE activity_meta_key = 'statistic_ref_id' AND activity_meta_value = %s LIMIT 1",
            $ref_id
        ));

        if ( ! $activity_id ) {
            $this->log("Activity ID not found for Ref $ref_id");
            return;
        }

        $graded_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT activity_meta_id, activity_meta_value FROM $meta_table WHERE activity_id = %d AND activity_meta_key = 'graded'",
            $activity_id
        ));

        if ( $graded_row ) {
            $meta = maybe_unserialize( $graded_row->activity_meta_value );
            if ( is_array($meta) ) {
                $updated = false;
                foreach ( $meta as $key => $data ) {
                    if ( isset($data['post_id']) && intval($data['post_id']) === intval($essay_post_id) ) {
                        $meta[$key]['status'] = 'graded';
                        $meta[$key]['points_awarded'] = $points;
                        $updated = true;
                    }
                }
                if ( $updated ) {
                    $wpdb->update(
                        $meta_table,
                        [ 'activity_meta_value' => serialize($meta) ],
                        [ 'activity_meta_id' => $graded_row->activity_meta_id ]
                    );
                    $this->log("Updated Graded Meta for Activity $activity_id");
                }
            }
        }
    }

    public function delayed_db_update($qid, $points, $quiz_id, $uid, $log_id) {
    global $wpdb; 
    
    error_log("[LD AI] Async Delayed Update triggered for Q: $qid, Points: $points");

    $table_stat = $wpdb->prefix . 'learndash_pro_quiz_statistic'; 
    $pro_id = get_post_meta($qid, 'question_pro_id', true); 
    
    if(!$pro_id) {
        error_log("[LD AI] Async Error: Cannot find Pro ID for Post $qid");
        return;
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT s.statistic_ref_id, r.create_time 
         FROM $table_stat s 
         JOIN {$wpdb->prefix}learndash_pro_quiz_statistic_ref r ON s.statistic_ref_id = r.statistic_ref_id 
         WHERE s.question_id = %d AND r.user_id = %d 
         ORDER BY s.statistic_ref_id DESC LIMIT 1", 
        $pro_id, $uid
    ));

    if ( ! $row ) {
        error_log("[LD AI] Async Warning: Statistic row not created yet via LD core. Sleeping 2s...");
        sleep(2);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT s.statistic_ref_id, r.create_time 
             FROM $table_stat s 
             JOIN {$wpdb->prefix}learndash_pro_quiz_statistic_ref r ON s.statistic_ref_id = r.statistic_ref_id 
             WHERE s.question_id = %d AND r.user_id = %d 
             ORDER BY s.statistic_ref_id DESC LIMIT 1", 
            $pro_id, $uid
        ));
    }

    if ( $row && $row->statistic_ref_id ) {
        error_log("[LD AI] Async Found Ref ID: " . $row->statistic_ref_id);
        
        $time_diff = time() - intval($row->create_time);
        if ( $time_diff > 60 ) { 
            error_log("[LD AI] Async Abort: Found stat ref but it is too old ({$time_diff}s ago). Assuming new row creation failed.");
            return; 
        } 
        
        $ref_id = $row->statistic_ref_id;
        $this->update_statistic_db($ref_id, $qid, $points, $quiz_id, $uid);
        
        $wpdb->update($wpdb->prefix . 'ld_ai_logs', ['statistic_ref_id' => $ref_id], ['id' => $log_id]);
    } else {
        error_log("[LD AI] Async Failed: Still cannot find statistic row after sleep.");
    }
}

    private function update_statistic_db($ref_id, $qid, $points, $quiz_id, $uid) {
        global $wpdb; 
        $pro_id = get_post_meta($qid, 'question_pro_id', true); 
        if(!$pro_id) return;
        $is_correct = ($points > 0.1) ? 1 : 0; 
        $wpdb->update($wpdb->prefix . 'learndash_pro_quiz_statistic', [ 'points' => $points, 'correct_count' => $is_correct, 'incorrect_count' => (1 - $is_correct) ], [ 'statistic_ref_id' => $ref_id, 'question_id' => $pro_id ]);
        $this->recalculate_total_quiz_score($ref_id);
    }

    public function recalculate_total_quiz_score( $ref_id ) {
        global $wpdb;
        $table_stat = $wpdb->prefix . 'learndash_pro_quiz_statistic';
        $meta_table = $wpdb->prefix . 'learndash_user_activity_meta';

        $earned_points = floatval($wpdb->get_var( $wpdb->prepare( "SELECT SUM(points) FROM $table_stat WHERE statistic_ref_id = %d", $ref_id ) ));
        
        $table_question = $wpdb->prefix . 'learndash_pro_quiz_question';
        $total_max_points = floatval($wpdb->get_var( $wpdb->prepare("SELECT SUM(q.points) FROM $table_question q INNER JOIN $table_stat s ON q.id = s.question_id WHERE s.statistic_ref_id = %d", $ref_id)));
        if ($total_max_points <= 0) $total_max_points = 1; 

        $percentage = ($earned_points / $total_max_points) * 100;
        if ($percentage > 100) $percentage = 100;

        $correct_count = intval($wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_stat WHERE statistic_ref_id = %d AND correct_count = 1", $ref_id ) ));

        $activity_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT activity_id FROM $meta_table WHERE activity_meta_key = 'statistic_ref_id' AND activity_meta_value = %s LIMIT 1",
            $ref_id
        ));

        if ( $activity_id ) {
            $this->update_single_activity_meta($activity_id, 'score', $correct_count);
            $this->update_single_activity_meta($activity_id, 'points', $earned_points);
            $this->update_single_activity_meta($activity_id, 'total_points', $total_max_points);
            $this->update_single_activity_meta($activity_id, 'percentage', round($percentage, 2));
            $this->update_single_activity_meta($activity_id, 'count', $correct_count);
            
            $this->log("Recalculated Stats for Activity $activity_id: Points=$earned_points, Percent=$percentage");
        }
    }

    private function update_single_activity_meta($activity_id, $key, $value) {
        global $wpdb;
        $table = $wpdb->prefix . 'learndash_user_activity_meta';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT activity_meta_id FROM $table WHERE activity_id = %d AND activity_meta_key = %s", $activity_id, $key));
        
        if ($exists) {
            $wpdb->update($table, ['activity_meta_value' => $value], ['activity_meta_id' => $exists]);
        } else {
            $wpdb->insert($table, ['activity_id' => $activity_id, 'activity_meta_key' => $key, 'activity_meta_value' => $value]);
        }
    }
}
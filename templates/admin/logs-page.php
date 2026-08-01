<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e( 'System Logs', 'learndash-ai-grader' ); ?></h1>
    
    <!-- Button Clear Logs -->
    <form method="post" style="display:inline-block; margin-left: 10px;" onsubmit="return confirm('Are you sure you want to delete ALL logs?');">
        <?php wp_nonce_field( 'ld_ai_clear_logs_nonce' ); ?>
        <input type="hidden" name="ld_ai_clear_logs" value="1">
        <button type="submit" class="button button-secondary delete"><?php _e( 'Clear All Logs', 'learndash-ai-grader' ); ?></button>
    </form>

    <p class="description">
        <?php _e( 'Logs are automatically retained for 7 days.', 'learndash-ai-grader' ); ?>
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="50">ID</th>
                <th width="140">Date</th>
                <th width="80">Status</th>
                <th width="120">Provider</th>
                <th>Details (Prompt / Error / Result)</th>
                <th width="80">Score</th>
                <th width="100">Rating</th> <!-- CỘT MỚI THÊM -->
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $logs ) ) : ?>
                <tr>
                    <td colspan="7"><?php _e( 'No logs found.', 'learndash-ai-grader' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <?php
                        // Truy vấn nhanh Rating của Log này
                        global $wpdb;
                        $rating_data = $wpdb->get_row( $wpdb->prepare( 
                            "SELECT rating, comment FROM {$wpdb->prefix}ld_ai_student_feedback WHERE log_id = %d", 
                            $log->id 
                        ));
                    ?>
                    <tr>
                        <td>#<?php echo esc_html( $log->id ); ?></td>
                        <td><?php echo esc_html( $log->created_at ); ?></td>
                        <td>
                            <?php 
                                $status_color = 'grey';
                                if ($log->status === 'completed') $status_color = 'green';
                                if ($log->status === 'failed') $status_color = 'red';
                            ?>
                            <span style="color:white; background:<?php echo $status_color; ?>; padding: 2px 6px; border-radius: 4px; font-size: 11px; text-transform: uppercase;">
                                <?php echo esc_html( $log->status ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $log->provider . ' / ' . $log->model ); ?></td>
                        <td>
                            <details>
                                <summary style="cursor:pointer; color:#0073aa;">View Data</summary>
                                <div style="margin-top:5px; padding:10px; background:#f0f0f1; font-family:monospace; font-size:11px; max-height: 200px; overflow:auto;">
                                    <?php if($log->error_message): ?>
                                        <div style="color:red; font-weight:bold;">ERROR: <?php echo esc_html($log->error_message); ?></div>
                                    <?php endif; ?>
                                    
                                    <strong>Prompt:</strong> <?php echo esc_html( mb_strimwidth($log->input_prompt, 0, 100, '...') ); ?><br>
                                    <strong>Student Ans:</strong> <?php echo esc_html( mb_strimwidth($log->student_response, 0, 100, '...') ); ?><br>
                                    <strong>AI Feedback:</strong> <?php echo esc_html( mb_strimwidth($log->ai_feedback_text, 0, 100, '...') ); ?>
                                </div>
                            </details>
                        </td>
                        <td><strong><?php echo esc_html( $log->ai_score ); ?></strong></td>
                        
                        <!-- HIỂN THỊ RATING -->
                        <td>
                            <?php if ( $rating_data ) : ?>
                                <?php if ( $rating_data->rating == 5 ) : ?>
                                    <span style="color: green; font-size: 18px;" title="Helpful">👍</span>
                                <?php elseif ( $rating_data->rating == 1 ) : ?>
                                    <span style="color: red; font-size: 18px;" title="Not Helpful">👎</span>
                                <?php endif; ?>
                                
                                <?php if ( ! empty( $rating_data->comment ) ) : ?>
                                    <br><small><i>"<?php echo esc_html($rating_data->comment); ?>"</i></small>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color: #ccc;">-</span>
                            <?php endif; ?>
                        </td>

                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
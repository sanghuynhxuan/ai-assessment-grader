<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e( 'AI Grader Analytics', 'learndash-ai-grader' ); ?></h1>
    
    <!-- Summary Cards -->
    <div class="ld-ai-dashboard-widgets" style="display: flex; gap: 20px; margin: 20px 0;">
        <div class="card" style="flex: 1; padding: 20px; text-align: center;">
            <h2 style="margin:0; font-size: 30px;" id="stat-total-graded">-</h2>
            <p><?php _e( 'Total Quizzes Graded', 'learndash-ai-grader' ); ?></p>
        </div>
        <div class="card" style="flex: 1; padding: 20px; text-align: center;">
            <h2 style="margin:0; font-size: 30px; color: #d63638;">$<span id="stat-total-cost">-</span></h2>
            <p><?php _e( 'Estimated Cost', 'learndash-ai-grader' ); ?></p>
        </div>
        <div class="card" style="flex: 1; padding: 20px; text-align: center;">
            <h2 style="margin:0; font-size: 30px; color: #00a32a;" id="stat-avg-score">-</h2>
            <p><?php _e( 'Average Score', 'learndash-ai-grader' ); ?></p>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display: flex; gap: 20px;">
        <div class="card" style="flex: 2; padding: 20px;">
            <h3><?php _e( 'Grading Volume (Last 7 Days)', 'learndash-ai-grader' ); ?></h3>
            <canvas id="chartDailyUsage"></canvas>
        </div>
        <div class="card" style="flex: 1; padding: 20px;">
            <h3><?php _e( 'AI Provider Usage', 'learndash-ai-grader' ); ?></h3>
            <canvas id="chartProviderDist"></canvas>
        </div>
    </div>

    <!-- Recent Logs Table -->
    <div class="card" style="margin-top: 20px; padding: 0 20px 20px;">
        <h3><?php _e( 'Recent Activity Logs', 'learndash-ai-grader' ); ?></h3>
        <table class="wp-list-table widefat fixed striped" id="table-recent-logs">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Provider</th>
                    <th>Model</th>
                    <th>Score</th>
                    <th>Cost ($)</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="6">Loading data...</td></tr>
            </tbody>
        </table>
    </div>
</div>
<div class="wrap">
    <h1><?php _e( 'AI Grading Sandbox (Testing Mode)', 'learndash-ai-grader' ); ?></h1>
    <p><?php _e( 'Use this tool to test your prompts and grading logic without affecting real student scores.', 'learndash-ai-grader' ); ?></p>

    <div style="display: flex; gap: 20px; margin-top: 20px;">
        <!-- Left Column: Inputs -->
        <div class="card" style="flex: 1; padding: 20px;">
            <h2 class="title"><?php _e( 'Input Data', 'learndash-ai-grader' ); ?></h2>
            
            <form id="ld-ai-test-form">
                <table class="form-table">
                    <tr>
                        <th><label>Provider & Model</label></th>
                        <td>
                            <select id="test-provider">
                                <option value="openai">OpenAI</option>
                                <option value="anthropic" disabled>Anthropic (Claude) - Coming Soon</option>
                                <option value="gemini" disabled>Google Gemini - Coming Soon</option>
                            </select>
                            <select id="test-model">
                                <option value="gpt-4o">GPT-4o</option>
                                <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Question Text</label></th>
                        <td>
                            <textarea id="test-question" class="large-text" rows="3" placeholder="E.g: Explain the theory of relativity..."></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Reference / Rubric</label></th>
                        <td>
                            <textarea id="test-rubric" class="large-text" rows="3" placeholder="Key points required: E=mc^2, time dilation..."></textarea>
                            <p class="description">This acts as the "Correct Answer" or grading instructions.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Student Answer</label></th>
                        <td>
                            <textarea id="test-answer" class="large-text" rows="5" placeholder="Student's response here..."></textarea>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="button" id="btn-run-test" class="button button-primary button-large">
                        <?php _e( 'Run Test', 'learndash-ai-grader' ); ?>
                    </button>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                </p>
            </form>
        </div>

        <!-- Right Column: Results -->
        <div class="card" style="flex: 1; padding: 20px; background: #f0f6fc;">
            <h2 class="title"><?php _e( 'AI Result', 'learndash-ai-grader' ); ?></h2>
            
            <div id="test-result-container" style="display: none;">
                <div style="margin-bottom: 20px; padding: 10px; background: #fff; border-left: 4px solid #72aee6;">
                    <strong>Score:</strong> <span id="res-score" style="font-size: 20px; font-weight: bold;">--</span> / 100
                </div>
                
                <div style="margin-bottom: 20px;">
                    <strong>Feedback:</strong>
                    <div id="res-feedback" style="background: #fff; padding: 15px; border: 1px solid #ddd; margin-top: 5px;"></div>
                </div>

                <div style="font-size: 0.9em; color: #666;">
                    <strong>Tokens Used:</strong> <span id="res-tokens">--</span><br>
                    <strong>Estimated Cost:</strong> $<span id="res-cost">--</span>
                </div>
                
                <hr>
                <details>
                    <summary>View Raw JSON</summary>
                    <pre id="res-raw" style="background: #eaeaea; padding: 10px; overflow-x: auto;"></pre>
                </details>
            </div>

            <div id="test-placeholder" style="color: #999; text-align: center; padding-top: 50px;">
                Run a test to see results here.
            </div>
        </div>
    </div>
</div>
<div class="wrap">
    <h1>LearnDash AI Grader Settings</h1>
    <form method="post" action="options.php">
        <?php
            settings_fields( 'ld_ai_grader_settings_group' );
            $options = get_option( 'ld_ai_grader_settings' );
            $models = \LearnDashAIGrader\Admin\Settings::get_supported_models();
        ?>

        <div class="card" style="padding: 20px; max-width: 800px; margin-top: 20px;">
            <h2>API Keys</h2>
            <table class="form-table">
                <tr>
                    <th>OpenAI API Key</th>
                    <td><input type="password" name="ld_ai_grader_settings[openai_key]" value="<?php echo !empty($options['openai_key']) ? '********************' : ''; ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Anthropic API Key</th>
                    <td><input type="password" name="ld_ai_grader_settings[anthropic_key]" value="<?php echo !empty($options['anthropic_key']) ? '********************' : ''; ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Google Gemini API Key</th>
                    <td><input type="password" name="ld_ai_grader_settings[gemini_key]" value="<?php echo !empty($options['gemini_key']) ? '********************' : ''; ?>" class="regular-text"></td>
                </tr>
            </table>
        </div>

        <div class="card" style="padding: 20px; max-width: 800px; margin-top: 20px;">
            <h2>Global Model Selection</h2>
            <table class="form-table">
                <tr>
                    <th>Active Model</th>
                    <td>
                        <select name="ld_ai_grader_settings[active_model]" style="min-width: 300px;">
                            <?php foreach ($models as $provider_key => $data): ?>
                                <optgroup label="<?php echo esc_attr($data['label']); ?>">
                                    <?php foreach ($data['models'] as $model_key => $model_name): ?>
                                        <?php $val = $provider_key . ':' . $model_key; ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected($options['active_model'] ?? '', $val); ?>>
                                            <?php echo esc_html($model_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">This model will be used for all questions unless overridden in the question settings.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card" style="padding: 20px; max-width: 800px; margin-top: 20px;">
            <h2>System Prompts</h2>
            <textarea name="ld_ai_grader_settings[global_prompt_essay]" rows="5" class="large-text"><?php echo esc_textarea( $options['global_prompt_essay'] ?? '' ); ?></textarea>
        </div>

        <?php submit_button(); ?>
    </form>
</div>
<?php
namespace LearnDashAIGrader\Admin;

class QuestionMetabox {
    public function init() {
        add_action( 'add_meta_boxes', array( $this, 'add_custom_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_custom_meta_box' ) );
    }

    public function add_custom_meta_box() {
        add_meta_box( 'ld_ai_grader_box', 'AI Grader Options', array( $this, 'render_meta_box' ), 'sfwd-question', 'normal', 'high' );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'ld_ai_save_meta', 'ld_ai_meta_nonce' );
        $override = get_post_meta( $post->ID, '_ld_ai_model_override', true );
        $prompt = get_post_meta( $post->ID, '_ld_ai_custom_prompt', true );
        $models = Settings::get_supported_models();
        ?>
        <p>
            <label><strong>Model Override:</strong></label><br>
            <select name="ld_ai_model_override" style="width: 100%; max-width: 400px;">
                <option value="">-- Use Global Default --</option>
                <?php foreach ($models as $provider_key => $data): ?>
                    <optgroup label="<?php echo esc_attr($data['label']); ?>">
                        <?php foreach ($data['models'] as $model_key => $model_name): ?>
                            <?php $val = $provider_key . ':' . $model_key; ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($override, $val); ?>>
                                <?php echo esc_html($model_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label><strong>Custom System Prompt:</strong></label><br>
            <textarea name="ld_ai_custom_prompt" rows="4" style="width:100%;"><?php echo esc_textarea($prompt); ?></textarea>
        </p>
        <?php
    }

    public function save_custom_meta_box( $post_id ) {
        if ( ! isset( $_POST['ld_ai_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ld_ai_meta_nonce'], 'ld_ai_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        if ( isset( $_POST['ld_ai_model_override'] ) ) {
            update_post_meta( $post_id, '_ld_ai_model_override', sanitize_text_field( $_POST['ld_ai_model_override'] ) );
        }
        if ( isset( $_POST['ld_ai_custom_prompt'] ) ) {
            update_post_meta( $post_id, '_ld_ai_custom_prompt', sanitize_textarea_field( $_POST['ld_ai_custom_prompt'] ) );
        }
    }
}
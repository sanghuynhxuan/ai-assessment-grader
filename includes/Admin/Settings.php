<?php

namespace LearnDashAIGrader\Admin;

use LearnDashAIGrader\Core\Encryption;

class Settings {

    private $option_name = 'ld_ai_grader_settings';

    public static function get_supported_models() {
        return [
            'openai' => [
                'label' => 'OpenAI',
                'models' => [
                    'gpt-4o' => 'GPT-4o (Best)',
                    'gpt-4o-mini' => 'GPT-4o Mini (Fast/Cheap)',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                ]
            ],
            'anthropic' => [
                'label' => 'Anthropic (Claude)',
                'models' => [
                    'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet',
                    'claude-3-opus-20240229' => 'Claude 3 Opus',
                    'claude-3-haiku-20240307' => 'Claude 3 Haiku',
                ]
            ],
            'gemini' => [
                'label' => 'Google Gemini',
                'models' => [
                    'gemini-1.5-pro' => 'Gemini 1.5 Pro',
                    'gemini-1.5-flash' => 'Gemini 1.5 Flash',
                ]
            ]
        ];
    }

    public function init() {
        register_setting( 'ld_ai_grader_settings_group', $this->option_name, array( $this, 'sanitize_settings' ) );
    }

    public function render_settings_page() {
        $options = get_option( $this->option_name );
        $openai_key = isset($options['openai_key']) ? Encryption::decrypt($options['openai_key']) : '';
        $anthropic_key = isset($options['anthropic_key']) ? Encryption::decrypt($options['anthropic_key']) : '';
        $gemini_key = isset($options['gemini_key']) ? Encryption::decrypt($options['gemini_key']) : '';
        
        require_once LD_AI_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }

    public function sanitize_settings( $input ) {
        $new_input = array();
        $existing = get_option( $this->option_name );

        if ( ! empty( $input['openai_key'] ) && strpos($input['openai_key'], '***') === false ) {
             $new_input['openai_key'] = Encryption::encrypt( sanitize_text_field( $input['openai_key'] ) );
        } else { $new_input['openai_key'] = $existing['openai_key'] ?? ''; }

        if ( ! empty( $input['anthropic_key'] ) && strpos($input['anthropic_key'], '***') === false ) {
             $new_input['anthropic_key'] = Encryption::encrypt( sanitize_text_field( $input['anthropic_key'] ) );
        } else { $new_input['anthropic_key'] = $existing['anthropic_key'] ?? ''; }

        if ( ! empty( $input['gemini_key'] ) && strpos($input['gemini_key'], '***') === false ) {
             $new_input['gemini_key'] = Encryption::encrypt( sanitize_text_field( $input['gemini_key'] ) );
        } else { $new_input['gemini_key'] = $existing['gemini_key'] ?? ''; }

        $new_input['active_model'] = sanitize_text_field( $input['active_model'] ?? 'openai:gpt-4o' );
        
        $new_input['global_prompt_essay'] = sanitize_textarea_field( $input['global_prompt_essay'] ?? '' );
        
        return $new_input;
    }
}
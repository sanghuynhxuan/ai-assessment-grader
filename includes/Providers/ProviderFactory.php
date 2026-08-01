<?php
namespace LearnDashAIGrader\Providers;

use LearnDashAIGrader\Core\Encryption;

class ProviderFactory {

    public static function get_provider( $provider_slug ) {
        $settings = get_option( 'ld_ai_grader_settings' );
        
        switch ( $provider_slug ) {
            case 'openai':
                $key = isset($settings['openai_key']) ? Encryption::decrypt($settings['openai_key']) : '';
                if ( empty($key) ) throw new \Exception('OpenAI API Key missing.');
                return new OpenAIProvider( $key );

            case 'anthropic':
                $key = isset($settings['anthropic_key']) ? Encryption::decrypt($settings['anthropic_key']) : '';
                if ( empty($key) ) throw new \Exception('Anthropic API Key missing.');
                return new AnthropicProvider( $key );

            case 'gemini':
                $key = isset($settings['gemini_key']) ? Encryption::decrypt($settings['gemini_key']) : '';
                if ( empty($key) ) throw new \Exception('Gemini API Key missing.');
                return new GeminiProvider( $key );

            default:
                throw new \Exception( "Provider '$provider_slug' not supported." );
        }
    }
}
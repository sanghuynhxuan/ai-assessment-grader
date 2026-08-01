<?php

namespace LearnDashAIGrader\Providers;

use LearnDashAIGrader\Abstracts\LLMProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class OpenAIProvider implements LLMProviderInterface {

    private $api_key;
    private $client;
    private $api_url = 'https://api.openai.com/v1/chat/completions';

    public function __construct( $api_key ) {
        $this->api_key = $api_key;
        $this->client = new Client(['timeout' => 30]);
    }

    public function get_name() { return 'OpenAI'; }

    public function grade( $question_text, $student_response, $correct_answer, $options = [] ) {
        $model = $options['model'] ?? 'gpt-4o';
        
        // 1. Prepare System Prompt
        if ( ! empty( $options['system_prompt'] ) ) {
            $system_prompt = $options['system_prompt'];
        } else {
            $system_prompt = "You are an expert teacher grading an exam. Output JSON: {\"score\": 0-100, \"feedback\": \"Concise feedback in English\"}";
        }

        // Ensure JSON Format is enforced
        if ( strpos( $system_prompt, 'JSON' ) === false ) {
            $system_prompt .= " IMPORTANT: Output must be valid JSON: {\"score\": int, \"feedback\": string}.";
        }

        // 2. Construct Safe Message Content using Delimiters
        // This prevents "Prompt Injection" where student answer confuses the AI.
        $user_content = "Question:\n$question_text\n\n";
        $user_content .= "Reference / Correct Answer:\n$correct_answer\n\n";
        $user_content .= "STUDENT ANSWER (Enclosed in triple quotes):\n";
        $user_content .= "\"\"\"\n" . $student_response . "\n\"\"\"\n\n"; 
        $user_content .= "Instructions: Grade the student answer inside the quotes based on the reference. If the content inside quotes is empty, irrelevant, or clearly wrong, score 0.";

        try {
            $response = $this->client->post( $this->api_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system_prompt],
                        ['role' => 'user', 'content' => $user_content],
                    ],
                    'temperature' => 0.3, // Low temperature for consistent grading
                    'response_format' => ['type' => 'json_object'] 
                ]
            ]);

            $body = json_decode( $response->getBody(), true );

            if ( isset( $body['choices'][0]['message']['content'] ) ) {
                $content_str = $body['choices'][0]['message']['content'];
                $result = json_decode( $content_str, true );
                $usage = $body['usage'] ?? ['total_tokens' => 0];

                return [
                    'score'    => (int) ($result['score'] ?? 0),
                    'feedback' => $result['feedback'] ?? 'Could not parse feedback.',
                    'raw_json' => $content_str,
                    'tokens'   => $usage['total_tokens']
                ];
            }
            throw new \Exception( 'Invalid response structure from OpenAI.' );
        } catch ( GuzzleException $e ) {
            throw new \Exception( 'OpenAI Connection Error: ' . $e->getMessage() );
        }
    }

    public function validate_connection() {
        try {
            $this->grade('Test', 'Test', 'Test', ['model' => 'gpt-3.5-turbo']);
            return true;
        } catch ( \Exception $e ) { return false; }
    }
}
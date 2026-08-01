<?php
namespace LearnDashAIGrader\Providers;

use LearnDashAIGrader\Abstracts\LLMProviderInterface;
use GuzzleHttp\Client;

class AnthropicProvider implements LLMProviderInterface {
    private $api_key;
    private $client;
    private $api_url = 'https://api.anthropic.com/v1/messages';

    public function __construct( $api_key ) {
        $this->api_key = $api_key;
        $this->client = new Client(['timeout' => 60]);
    }

    public function get_name() { return 'Anthropic'; }

    public function grade( $question, $student_ans, $rubric, $options = [] ) {
        $model = $options['model'] ?? 'claude-3-5-sonnet-20240620';
        $system_prompt = $options['system_prompt'] ?? 'You are a grader.';
        $system_prompt .= " Output strictly JSON: {\"score\": 0-100, \"feedback\": \"...\"}";

        $prompt_content = "Question: $question\nRubric: $rubric\n\nStudent Answer:\n$student_ans";

        try {
            $response = $this->client->post( $this->api_url, [
                'headers' => [
                    'x-api-key' => $this->api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json'
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 1024,
                    'system' => $system_prompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt_content]
                    ]
                ]
            ]);

            $body = json_decode( $response->getBody(), true );
            $raw_content = $body['content'][0]['text'];
            
            $json_str = $raw_content;
            if (preg_match('/\{.*\}/s', $raw_content, $matches)) {
                $json_str = $matches[0];
            }
            $result = json_decode($json_str, true);

            return [
                'score' => $result['score'] ?? 0,
                'feedback' => $result['feedback'] ?? $raw_content,
                'raw_json' => $raw_content,
                'tokens' => ($body['usage']['input_tokens'] + $body['usage']['output_tokens'])
            ];
        } catch (\Exception $e) {
            throw new \Exception('Anthropic Error: ' . $e->getMessage());
        }
    }

    public function validate_connection() { return true; }
}
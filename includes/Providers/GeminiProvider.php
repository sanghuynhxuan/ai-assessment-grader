<?php
namespace LearnDashAIGrader\Providers;

use LearnDashAIGrader\Abstracts\LLMProviderInterface;
use GuzzleHttp\Client;

class GeminiProvider implements LLMProviderInterface {
    private $api_key;
    private $client;
    private $base_url = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct( $api_key ) {
        $this->api_key = $api_key;
        $this->client = new Client(['timeout' => 60]);
    }

    public function get_name() { return 'Gemini'; }

    public function grade( $question, $student_ans, $rubric, $options = [] ) {
        $model = $options['model'] ?? 'gemini-1.5-pro';
        $system = $options['system_prompt'] ?? 'You are a grader.';
        $system .= " Return JSON: {\"score\": number, \"feedback\": string}";

        $final_prompt = "$system\n\nQuestion: $question\nRubric: $rubric\nStudent Answer: $student_ans";

        try {
            $url = $this->base_url . $model . ':generateContent?key=' . $this->api_key;
            
            $response = $this->client->post( $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'contents' => [
                        ['parts' => [['text' => $final_prompt]]]
                    ],
                    'generationConfig' => ['responseMimeType' => 'application/json']
                ]
            ]);

            $body = json_decode( $response->getBody(), true );
            $text = $body['candidates'][0]['content']['parts'][0]['text'];
            $result = json_decode($text, true);

            // Gemini usage metadata varies, approximate simple count if missing
            $tokens = 0; 
            if(isset($body['usageMetadata']['totalTokenCount'])) {
                $tokens = $body['usageMetadata']['totalTokenCount'];
            }

            return [
                'score' => $result['score'] ?? 0,
                'feedback' => $result['feedback'] ?? $text,
                'raw_json' => $text,
                'tokens' => $tokens
            ];
        } catch (\Exception $e) {
            throw new \Exception('Gemini Error: ' . $e->getMessage());
        }
    }

    public function validate_connection() { return true; }
}
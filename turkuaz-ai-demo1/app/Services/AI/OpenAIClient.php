<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIClient
{
    /**
     * Minimal chat-completions wrapper. Isolated here so swapping providers
     * (or adding streaming, retries, function calling) touches one file only.
     */
    public function chat(array $messages): string
    {
        $apiKey = config('assistant.openai_api_key');

        if (blank($apiKey)) {
            throw new RuntimeException('OpenAI API key is not configured (OPENAI_API_KEY).');
        }

        $response = Http::withToken($apiKey)
            ->timeout(config('assistant.timeout', 30))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('assistant.model', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => config('assistant.temperature', 0.3),
                'max_tokens' => config('assistant.max_tokens', 700),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI request failed: ' . $response->status() . ' ' . $response->body());
        }

        return trim($response->json('choices.0.message.content', ''));
    }
}

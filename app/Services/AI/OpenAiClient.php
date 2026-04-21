<?php

namespace App\Services\AI;

use App\Models\GoogleIntegrationSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient
{
    public function chatJson(array $messages, ?string $model = null): array
    {
        $apiKey = GoogleIntegrationSettings::openAiApiKey();
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $response = Http::timeout(45)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model ?: (string) config('services.openai.model', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => 0.2,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed: '.$response->status());
        }

        return $response->json();
    }
}

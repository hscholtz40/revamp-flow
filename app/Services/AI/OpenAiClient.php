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

    public function transcribe(string $absolutePath, string $filename = 'audio.webm'): string
    {
        $apiKey = GoogleIntegrationSettings::openAiApiKey();
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not read the voice note file.');
        }

        $payload = [
            'model' => (string) config('services.openai.transcription_model', 'gpt-4o-mini-transcribe'),
            'response_format' => 'json',
            'temperature' => 0,
        ];

        $language = config('services.openai.transcription_language');
        if (is_string($language) && $language !== '') {
            $payload['language'] = $language;
        }

        try {
            $response = Http::timeout(90)
                ->withToken($apiKey)
                ->attach('file', $handle, $filename)
                ->post('https://api.openai.com/v1/audio/transcriptions', $payload);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $response->successful()) {
            $message = (string) data_get($response->json(), 'error.message', '');
            throw new RuntimeException(
                $message !== ''
                    ? 'OpenAI transcription failed: '.$message
                    : 'OpenAI transcription failed: '.$response->status()
            );
        }

        $text = trim((string) data_get($response->json(), 'text', ''));
        if ($text === '') {
            throw new RuntimeException('No speech was detected in the voice note.');
        }

        return $text;
    }
}

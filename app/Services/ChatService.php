<?php

namespace App\Services;

use App\Services\OpenRouter\Client;

class ChatService
{
    public function __construct(private Client $client) {}

    public function sendMessage(array $messages, string $model): string
    {
        $response = $this->client->post('/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 1.0,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Erreur inconnue');
            throw new \RuntimeException("Erreur API: {$error}");
        }

        return $response->json('choices.0.message.content', '');
    }

    public function getModels(): array
    {
        $response = $this->client->get('/models');

        return $response->json('data', []);
    }
}

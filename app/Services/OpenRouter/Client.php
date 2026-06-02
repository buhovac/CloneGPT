<?php

declare(strict_types=1);

namespace App\Services\OpenRouter;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Client
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->baseUrl = rtrim(config('services.openrouter.base_url'), '/');
    }

    /**
     * Effectue une requête GET.
     */
    public function get(string $endpoint): Response
    {
        return $this->request()->get($this->baseUrl . $endpoint);
    }

    /**
     * Effectue une requête POST.
     */
    public function post(string $endpoint, array $data = []): Response
    {
        return $this->request()->post($this->baseUrl . $endpoint, $data);
    }

    /**
     * Configure la requête HTTP avec les headers nécessaires.
     */
    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ])->timeout(120);
    }
}

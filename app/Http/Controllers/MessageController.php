<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\SimpleAskService;
use App\Services\SimpleAskStreamService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    public function __construct(
        private SimpleAskService $askService,
        private SimpleAskStreamService $streamService,
    ) {}

    public function store(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'content'     => 'required|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->content,
        ]);

        $history = $conversation->messages
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $responseText = $this->askService->sendMessage(
            messages: $history,
            model: $conversation->model
        );

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $responseText,
        ]);

        $isFirstMessage = $conversation->messages()->count() === 2;
        if ($isFirstMessage && $conversation->title === 'Nouvelle conversation') {
            $titleResponse = $this->askService->sendMessage(
                messages: [[
                    'role' => 'user',
                    'content' => "Génère un titre court (max 6 mots) pour cette conversation dont voici le premier message : \"{$request->content}\". Réponds UNIQUEMENT avec le titre, sans guillemets ni ponctuation.",
                ]],
                model: $conversation->model
            );
            $conversation->update(['title' => trim($titleResponse)]);
        }

        $conversation->touch();

        return redirect()->route('chat.show', $conversation);
    }

    public function stream(Request $request, Conversation $conversation): StreamedResponse
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        $request->validate(['content' => 'required|string']);

        // 1. Spremi user poruku odmah
        $conversation->messages()->create([
            'role'    => 'user',
            'content' => $request->content,
        ]);

        // 2. Pripremi historiju (context window)
        $history = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $userContent = $request->content;

        return response()->stream(
            function () use ($history, $conversation, $userContent): void {
                // 3. Streami prema klijentu i skupljaj odgovor
                $fullResponse = $this->streamService->streamAndCollect(
                    messages: $history,
                    model: $conversation->model,
                    temperature: (float) ($validated['temperature'] ?? 1.0),
                );

                // 4. Spremi assistant odgovor — ukloni reasoning markere
                $cleanResponse = trim(preg_replace(
                    '/\[REASONING][\s\S]*?\[\/REASONING]/',
                    '',
                    $fullResponse
                ) ?? '');

                $conversation->messages()->create([
                    'role'    => 'assistant',
                    'content' => $cleanResponse ?: $fullResponse,
                ]);

                // 5. Auto-title na prvoj poruci
                $messageCount = $conversation->messages()->count();
                if ($messageCount === 2 && $conversation->title === 'Nouvelle conversation') {
                    $titleResponse = $this->askService->sendMessage(
                        messages: [[
                            'role'    => 'user',
                            'content' => "Génère un titre court (max 6 mots) pour cette conversation dont voici le premier message : \"{$userContent}\". Réponds UNIQUEMENT avec le titre, sans guillemets ni ponctuation.",
                        ]],
                        model: $conversation->model
                    );
                    $conversation->update(['title' => trim($titleResponse)]);
                }

                // 6. touch() za sortiranje
                $conversation->touch();
            },
            headers: [
                'Content-Type'      => 'text/plain; charset=utf-8',
                'Cache-Control'     => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }
}

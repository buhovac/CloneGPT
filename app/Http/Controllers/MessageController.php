<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\SimpleAskService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private SimpleAskService $askService) {}

    public function store(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        $request->validate(['content' => 'required|string']);

        // 1. Spremi user poruku
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->content,
        ]);

        // 2. Pripremi historiju za API (context window!)
        $history = $conversation->messages
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // 3. Pozovi API
        $responseText = $this->askService->sendMessage(
            messages: $history,
            model: $conversation->model
        );

        // 4. Spremi assistant odgovor
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $responseText,
        ]);

        // 5. Auto-generisanje naslova za prvu poruku
        $isFirstMessage = $conversation->messages()->count() === 2; // user + assistant
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

        // 6. touch() ažurira updated_at za sortiranje
        $conversation->touch();

        return redirect()->route('chat.show', $conversation);
    }
}

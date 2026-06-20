<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\SimpleAskService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConversationController extends Controller
{
    public function __construct(private SimpleAskService $askService) {}

    public function index()
    {
        $conversations = auth()->user()
            ->conversations()
            ->with('tags')
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'model', 'updated_at']);

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'models' => $this->askService->getModels(),
            'defaultModel' => auth()->user()->preferred_model ?? SimpleAskService::DEFAULT_MODEL,
            'allTags' => auth()->user()->tags()->orderBy('name')->get(),
        ]);
    }

    public function show(Conversation $conversation)
    {
        // Vérifier que la conversation appartient à l'utilisateur
        abort_unless($conversation->user_id === auth()->id(), 403);

        $conversations = auth()->user()
            ->conversations()
            ->with('tags')
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'model', 'updated_at']);

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'models' => $this->askService->getModels(),
            'defaultModel' => auth()->user()->preferred_model ?? SimpleAskService::DEFAULT_MODEL,
            'activeConversation' => $conversation->load('messages', 'tags'),
            'allTags' => auth()->user()->tags()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['model' => 'required|string']);

        $model = $request->model;

        // Enregistrer le modèle préféré pour l'utilisateur
        auth()->user()->update(['preferred_model' => $model]);

        $conversation = auth()->user()->conversations()->create([
            'title' => 'Nouvelle conversation',
            'model' => $model,
        ]);

        return redirect()->route('chat.show', $conversation);
    }

    public function updateModel(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        $request->validate(['model' => 'required|string']);

        $model = $request->model;

        // Enregistrez dans la table des utilisateurs et dans la table des conversations
        auth()->user()->update(['preferred_model' => $model]);
        $conversation->update(['model' => $model]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Créer un nouveau tag pour l'utilisateur courant.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        auth()->user()->tags()->firstOrCreate(
            ['name' => $validated['name']],
            ['color' => $validated['color'] ?? '#beef35'],
        );

        return back();
    }

    /**
     * Supprimer un tag (détache automatiquement via cascade).
     */
    public function destroy(Tag $tag)
    {
        abort_unless($tag->user_id === auth()->id(), 403);

        $tag->delete();

        return back();
    }

    /**
     * Attacher un tag à une conversation.
     */
    public function attach(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        $validated = $request->validate(['tag_id' => 'required|exists:tags,id']);

        $tag = Tag::findOrFail($validated['tag_id']);
        abort_unless($tag->user_id === auth()->id(), 403);

        // syncWithoutDetaching évite les doublons sans retirer les tags existants
        $conversation->tags()->syncWithoutDetaching([$tag->id]);

        return back();
    }

    /**
     * Détacher un tag d'une conversation.
     */
    public function detach(Conversation $conversation, Tag $tag)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);
        abort_unless($tag->user_id === auth()->id(), 403);

        $conversation->tags()->detach($tag->id);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class InstructionsController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('Instructions/Index', [
            'instructions' => [
                'custom_about'    => auth()->user()->custom_about,
                'custom_behavior' => auth()->user()->custom_behavior,
                'custom_commands' => auth()->user()->custom_commands,
            ],
        ]);
    }

    public function update(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'custom_about'    => 'nullable|string|max:2000',
            'custom_behavior' => 'nullable|string|max:2000',
            'custom_commands' => 'nullable|string|max:2000',
        ]);

        auth()->user()->update($request->only(
            'custom_about',
            'custom_behavior',
            'custom_commands',
        ));

        return back()->with('success', 'Instructions sauvegardées.');
    }
}

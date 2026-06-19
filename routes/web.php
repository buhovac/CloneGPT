<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AskController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\InstructionsController;
use App\Http\Controllers\AskStreamController;
use App\Http\Controllers\TagController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // exo 1
    Route::get('/ask', [AskController::class, 'index'])->name('ask.index');
    Route::post('/ask', [AskController::class, 'ask'])->name('ask.post');

    // exo 2
    Route::get('/chat', [ConversationController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversation}', [ConversationController::class, 'show'])->name('chat.show');
    Route::post('/chat', [ConversationController::class, 'store'])->name('chat.store');
    Route::patch('/chat/{conversation}/model', [ConversationController::class, 'updateModel'])->name('chat.model');
    Route::post('/chat/{conversation}/messages', [MessageController::class, 'store'])->name('chat.messages.store');
    Route::post('/chat/{conversation}/stream', [MessageController::class, 'stream'])
        ->name('chat.messages.stream');

    // exo 3
    Route::get('/instructions', [InstructionsController::class, 'index'])->name('instructions.index');
    Route::patch('/instructions', [InstructionsController::class, 'update'])->name('instructions.update');

    // exo 4
    Route::get('/ask-stream', [AskStreamController::class, 'index'])->name('stream.index');
    Route::post('/ask-stream', [AskStreamController::class, 'stream'])->name('stream.post');

    // Tags (bonus — N-N relation)
    Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
    Route::post('/chat/{conversation}/tags', [TagController::class, 'attach'])->name('tags.attach');
    Route::delete('/chat/{conversation}/tags/{tag}', [TagController::class, 'detach'])->name('tags.detach');
});

require __DIR__.'/settings.php';

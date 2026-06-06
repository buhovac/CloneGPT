<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AskController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\InstructionsController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Vježba 1
    Route::get('/ask', [AskController::class, 'index'])->name('ask.index');
    Route::post('/ask', [AskController::class, 'ask'])->name('ask.post');

    // Vježba 2
    Route::get('/chat', [ConversationController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversation}', [ConversationController::class, 'show'])->name('chat.show');
    Route::post('/chat', [ConversationController::class, 'store'])->name('chat.store');
    Route::patch('/chat/{conversation}/model', [ConversationController::class, 'updateModel'])->name('chat.model');
    Route::post('/chat/{conversation}/messages', [MessageController::class, 'store'])->name('chat.messages.store');

    // Vježba 3
    Route::get('/instructions', [InstructionsController::class, 'index'])->name('instructions.index');
    Route::patch('/instructions', [InstructionsController::class, 'update'])->name('instructions.update');
});

require __DIR__.'/settings.php';

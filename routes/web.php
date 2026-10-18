<?php

use App\Http\Controllers\CandidateController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TeamController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::post('/teams/join', [TeamController::class, 'join'])->name('teams.join');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
});

// 候補と返事（担当C）
Route::middleware('auth')->group(function () {
    Route::get('/teams/{team}/candidates', [CandidateController::class, 'index'])->name('candidates.index');
    Route::post('/teams/{team}/candidates', [CandidateController::class, 'store'])->name('candidates.store');
    Route::post('/candidates/{candidate}/answer', [CandidateController::class, 'answer'])->name('candidates.answer');
});

require __DIR__.'/auth.php';

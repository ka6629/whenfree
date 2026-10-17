<?php

use App\Http\Controllers\CandidateController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

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
});

// 候補と返事（担当C）
Route::middleware('auth')->group(function () {
    Route::get('/teams/{team}/candidates', [CandidateController::class, 'index'])->name('candidates.index');
    Route::post('/teams/{team}/candidates', [CandidateController::class, 'store'])->name('candidates.store');
    Route::post('/candidates/{candidate}/answer', [CandidateController::class, 'answer'])->name('candidates.answer');
});

require __DIR__.'/auth.php';

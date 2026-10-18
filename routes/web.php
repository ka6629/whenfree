<?php

use App\Http\Controllers\CandidateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamWeekController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::post('/teams/join', [TeamController::class, 'join'])->name('teams.join');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::get('/teams/{team}/week', [TeamWeekController::class, 'show'])->name('teams.week');
    Route::resource('schedules', ScheduleController::class)->except(['create', 'show']);
});

// 候補と返事（担当C）
Route::middleware('auth')->group(function () {
    Route::get('/teams/{team}/candidates', [CandidateController::class, 'index'])->name('candidates.index');
    Route::post('/teams/{team}/candidates', [CandidateController::class, 'store'])->name('candidates.store');
    Route::post('/candidates/{candidate}/answer', [CandidateController::class, 'answer'])->name('candidates.answer');
    Route::post('/candidates/{candidate}/status', [CandidateController::class, 'changeStatus'])->name('candidates.status');
});

require __DIR__.'/auth.php';

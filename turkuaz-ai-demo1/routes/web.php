<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('assistant.index');
});

Route::get('/dashboard', function (Illuminate\Http\Request $request) {
    return $request->user()->hasRole('administrator')
        ? redirect()->route('admin.dashboard')
        : redirect()->route('assistant.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php'; 

require_once __DIR__.'/admin.php';

require_once __DIR__.'/assistant.php';

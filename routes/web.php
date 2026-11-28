<?php

use App\Http\Controllers\ProfileController;
use App\Models\Triage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// CEN-15: pantallas mínimas de cada módulo para la redirección por rol y el control
// de acceso. Su funcionalidad llega en los Sprints 3 y 4 (docs/specs/auth/spec.md §4).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/pacientes', fn () => view('modules.placeholder', [
        'title' => 'Admisión de pacientes',
        'sprint' => 'Sprint 3',
    ]))->middleware('can:access-admission')->name('patients.index');

    // CEN-34: alta de pacientes (docs/specs/pacientes/spec.md).
    Route::view('/pacientes/nuevo', 'patients.create')
        ->middleware('can:access-admission')->name('patients.create');

    Route::get('/triage', fn () => view('triage.index'))
        ->middleware('can:access-triage')
        ->name('triage.index');

    // CEN-42: cola médica en tiempo real (docs/specs/consultas/spec.md).
    Route::view('/consultas', 'consultations.index')
        ->middleware('can:access-doctor')->name('consultations.index');

    // CEN-47: consultorio y cierre con diagnóstico presuntivo.
    Route::get('/consultas/{triage}', fn (Triage $triage) => view('consultations.show', ['triage' => $triage]))
        ->middleware('can:access-doctor')->name('consultations.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

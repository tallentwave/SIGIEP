<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('casos', CaseController::class)
        ->parameters(['casos' => 'case'])->except('destroy');
    Route::put('casos/{case}/gestao', [CaseController::class, 'manage'])->name('casos.manage');

    Route::post('casos/{case}/escutas', [SessionController::class, 'store'])->name('escutas.store');
    Route::get('escutas/{session}/editar', [SessionController::class, 'edit'])->name('escutas.edit');
    Route::put('escutas/{session}', [SessionController::class, 'update'])->name('escutas.update');

    Route::post('casos/{case}/encaminhamentos', [ReferralController::class, 'store'])->name('encaminhamentos.store');
    Route::patch('encaminhamentos/{referral}', [ReferralController::class, 'update'])->name('encaminhamentos.update');

    Route::get('auditoria', [AuditController::class, 'index'])->name('auditoria.index');
});

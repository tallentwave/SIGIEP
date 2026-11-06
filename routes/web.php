<?php

use App\Http\Controllers\Admin\MunicipalityController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ChildController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PublicIntakeController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RetentionController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

// Canal público de denúncia (sem login).
Route::get('/denuncia', [PublicIntakeController::class, 'index'])->name('denuncia.index');
Route::get('/denuncia/{slug}', [PublicIntakeController::class, 'create'])->name('denuncia.create');
Route::post('/denuncia/{slug}', [PublicIntakeController::class, 'store'])->middleware('throttle:5,1')->name('denuncia.store');
Route::get('/denuncia/{slug}/recebida', [PublicIntakeController::class, 'received'])->name('denuncia.recebida');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/esqueci-senha', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/esqueci-senha', [PasswordResetController::class, 'email'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/redefinir-senha/{token}', [PasswordResetController::class, 'form'])->name('password.reset');
    Route::post('/redefinir-senha', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Verificação em duas etapas (fora do middleware two-factor, senão haveria laço de redirecionamento).
    Route::get('/2fa/configurar', [TwoFactorController::class, 'setup'])->name('2fa.setup');
    Route::post('/2fa/configurar', [TwoFactorController::class, 'confirm'])->middleware('throttle:6,1')->name('2fa.confirm');
    Route::get('/2fa/desafio', [TwoFactorController::class, 'challenge'])->name('2fa.challenge');
    Route::post('/2fa/desafio', [TwoFactorController::class, 'verify'])->middleware('throttle:6,1')->name('2fa.verify');
});

Route::middleware(['auth', 'two-factor'])->group(function () {
    // Gerenciar/desativar 2FA só depois de passar pelo desafio (senha sozinha não basta).
    Route::get('/2fa', [TwoFactorController::class, 'manage'])->name('2fa.manage');
    Route::post('/2fa/codigos', [TwoFactorController::class, 'regenerate'])->name('2fa.regenerate');
    Route::delete('/2fa', [TwoFactorController::class, 'disable'])->name('2fa.disable');
    Route::get('/senha', [PasswordController::class, 'edit'])->name('senha.edit');
    Route::put('/senha', [PasswordController::class, 'update'])->name('senha.update');
});

Route::middleware(['auth', 'two-factor', 'password.changed'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('casos', CaseController::class)
        ->parameters(['casos' => 'case'])->except('destroy');
    Route::put('casos/{case}/gestao', [CaseController::class, 'manage'])->name('casos.manage');
    Route::post('casos/{case}/assumir', [CaseController::class, 'claim'])->name('casos.claim');
    Route::post('casos/{case}/fluxo', [CaseController::class, 'applyFlow'])->name('casos.fluxo');

    Route::get('atendidos', [ChildController::class, 'index'])->name('atendidos.index');
    Route::get('atendidos/{child}', [ChildController::class, 'show'])->name('atendidos.show');

    Route::get('retencao', [RetentionController::class, 'index'])->name('retencao.index');
    Route::post('casos/{case}/guarda-legal', [RetentionController::class, 'hold'])->name('retencao.hold');
    Route::post('casos/{case}/anonimizar', [RetentionController::class, 'anonymize'])->name('retencao.anonymize');

    Route::get('integracoes', [IntegrationController::class, 'index'])->name('integracoes.index');
    Route::post('integracoes/sinan/arquivo', [IntegrationController::class, 'sinanFile'])->name('integracoes.sinan.file');
    Route::post('casos/{case}/sinan/ficha', [IntegrationController::class, 'sinanSheet'])->name('integracoes.sinan.sheet');
    Route::get('casos/{case}/sinan/previa', [IntegrationController::class, 'sinanPreview'])->name('integracoes.sinan.preview');
    Route::post('casos/{case}/sinan/dispensar', [IntegrationController::class, 'sinanWaive'])->name('integracoes.sinan.waive');
    Route::post('integracoes/{dispatch}/concluir', [IntegrationController::class, 'confirm'])->name('integracoes.confirm');
    Route::post('casos/{case}/processos', [IntegrationController::class, 'storeProcess'])->name('integracoes.processos.store');
    Route::delete('processos/{process}', [IntegrationController::class, 'destroyProcess'])->name('integracoes.processos.destroy');
    Route::post('casos/{case}/justica/comunicacao', [IntegrationController::class, 'justicePdf'])->name('integracoes.justica.pdf');

    Route::get('relatorios/caso/{case}', [ReportController::class, 'case'])->name('relatorios.caso');
    Route::get('estatisticas', [StatsController::class, 'index'])->name('estatisticas.index');
    Route::get('estatisticas/pdf', [StatsController::class, 'pdf'])->name('estatisticas.pdf');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->except(['show', 'destroy']);
        Route::resource('municipios', MunicipalityController::class)->parameters(['municipios' => 'municipality'])->except(['show', 'destroy']);
        Route::get('municipios/{municipality}/fluxo', [MunicipalityController::class, 'flow'])->name('municipios.fluxo');
        Route::post('municipios/{municipality}/etapas', [MunicipalityController::class, 'storeStep'])->name('municipios.etapas.store');
        Route::put('municipios/{municipality}/etapas/{step}', [MunicipalityController::class, 'updateStep'])->name('municipios.etapas.update');
        Route::delete('municipios/{municipality}/etapas/{step}', [MunicipalityController::class, 'destroyStep'])->name('municipios.etapas.destroy');
    });

    Route::post('casos/{case}/escutas', [SessionController::class, 'store'])->name('escutas.store');
    Route::get('escutas/{session}/editar', [SessionController::class, 'edit'])->name('escutas.edit');
    Route::put('escutas/{session}', [SessionController::class, 'update'])->name('escutas.update');

    Route::post('casos/{case}/encaminhamentos', [ReferralController::class, 'store'])->name('encaminhamentos.store');
    Route::patch('encaminhamentos/{referral}', [ReferralController::class, 'update'])->name('encaminhamentos.update');

    Route::get('auditoria', [AuditController::class, 'index'])->name('auditoria.index');
});

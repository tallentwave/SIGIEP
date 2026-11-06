<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tentativas de acesso a caso/relato sem permissão ficam registradas.
        // (Exceções HTTP não passam por report(); o render roda antes da resposta 403.)
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (auth()->check()) {
                AuditLog::record(AuditAction::Denied, null, null, ['path' => $request->path()]);
            }

            return null; // segue para a página 403 padrão
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

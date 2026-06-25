<?php

use App\Http\Middleware\SetTenantFromUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Detrás del reverse proxy (aaPanel/nginx que termina el SSL): confiar en
        // los headers X-Forwarded-* para que Laravel detecte HTTPS y no genere
        // URLs http:// (causa del "Mixed Content" que bloqueaba los assets).
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->validateCsrfTokens(except: [
            'panel/login',
        ]);

        $middleware->web(append: [
            SetTenantFromUser::class,
        ]);

        // API pública del turnero: valida la API key de la marca.
        $middleware->alias([
            'brand.apikey' => \App\Http\Middleware\EnsureBrandApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();

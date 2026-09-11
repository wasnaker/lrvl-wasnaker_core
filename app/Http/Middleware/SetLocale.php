<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terapkan bahasa user (users.locale) ke app locale per request.
 *
 * Guard 'sanctum' di-resolve eksplisit karena middleware ini berjalan di
 * group 'api' sebelum auth:sanctum; hasilnya di-cache AuthManager sehingga
 * auth:sanctum tidak query ulang.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = auth('sanctum')->user()?->locale;

        if ($locale && in_array($locale, config('app.available_locales', []), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}

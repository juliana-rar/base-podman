<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanAccessScreen
{
    /**
     * Permet la petició si l'usuari és admin o el seu rol té atorgada la pantalla.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $screen): Response
    {
        if (! $request->user()?->canAccessScreen($screen)) {
            abort(403, 'No tens permís per accedir a aquesta secció.');
        }

        return $next($request);
    }
}

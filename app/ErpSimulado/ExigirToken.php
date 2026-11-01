<?php

namespace App\ErpSimulado;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigirToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $valido = $token !== null && TokenErp::where('hash', hash('sha256', $token))
            ->where('expira_em', '>', now())
            ->exists();

        if (! $valido) {
            return response()->json(['erro' => 'Token inválido ou expirado'], 401);
        }

        return $next($request);
    }
}

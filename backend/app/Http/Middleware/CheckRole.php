<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response; // Importamos la clase Response para tipar el retorno

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
public function handle(Request $request, Closure $next, string $role): Response
{
    // 1. Verificamos si el usuario tiene el rol que pedimos
    // Si el rol del usuario NO es igual al rol requerido ($role), le prohibimos el paso.
    if ($request->user()->role !== $role) {
        return response()->json(['message' => 'No tienes permiso para realizar esta acción.'], 403);
    }
    return $next($request);
}
}

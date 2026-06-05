<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Cek apakah user yang login memiliki role yang diizinkan.
     * Penggunaan di routes: middleware('role:admin') atau middleware('role:nasabah')
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->user()?->role !== $role) {
            return response()->json([
                'message' => 'Akses ditolak. Role tidak sesuai.',
            ], 403);
        }

        return $next($request);
    }
}
<?php

namespace App\Http\Middleware;

use App\Repositories\Contracts\Auth\UserRepositoryInterface;
use App\Services\Auth\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateJwtToken
{
    public function __construct(
        protected JwtTokenService $tokens,
        protected UserRepositoryInterface $users
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Token tidak ditemukan.'], 401);
        }

        try {
            $payload = $this->tokens->decode($token);
            $user = $this->users->findById((int) ($payload['sub'] ?? 0));

            if (! $user) {
                return response()->json(['message' => 'User tidak ditemukan.'], 401);
            }

            Auth::setUser($user);
            $request->attributes->set('jwt_token', $token);
            $request->attributes->set('jwt_payload', $payload);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return $next($request);
    }
}

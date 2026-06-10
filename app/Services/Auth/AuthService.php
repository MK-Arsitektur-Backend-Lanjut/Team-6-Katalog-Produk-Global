<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\Auth\UserRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $users,
        protected JwtTokenService $tokens
    ) {}

    public function register(array $data): User
    {
        return $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }

    public function login(array $credentials): array
    {
        $user = $this->users->findByEmail($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak valid.'],
            ]);
        }

        $token = $this->tokens->generate($user);

        Cache::store('redis')->put(
            "user:{$user->id}:profile",
            $user->only(['id', 'name', 'email']),
            900
        );

        return [
            ...$token,
            'user' => $user,
        ];
    }

    public function logout(string $token): void
    {
        $this->tokens->blacklist($token);
    }
}

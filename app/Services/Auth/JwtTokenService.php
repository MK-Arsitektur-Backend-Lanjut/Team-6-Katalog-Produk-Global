<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class JwtTokenService
{
    public function generate(User $user): array
    {
        $now = time();
        $ttl = (int) config('jwt.ttl', 3600);
        $expiresAt = $now + $ttl;

        $payload = [
            'iss' => config('jwt.issuer'),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt,
            'jti' => (string) Str::uuid(),
            'sub' => $user->id,
        ];

        $token = $this->encode($payload);

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttl,
            'expires_at' => $expiresAt,
        ];
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format.');
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;
        $expectedSignature = $this->sign("{$encodedHeader}.{$encodedPayload}");

        if (! hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Invalid token signature.');
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid token payload.');
        }

        if (($payload['nbf'] ?? 0) > time()) {
            throw new RuntimeException('Token is not active yet.');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new RuntimeException('Token has expired.');
        }

        if ($this->isBlacklisted($token)) {
            throw new RuntimeException('Token has been blacklisted.');
        }

        return $payload;
    }

    public function blacklist(string $token): void
    {
        $payload = $this->decodeIgnoringBlacklist($token);
        $secondsUntilExpiry = max(1, ((int) ($payload['exp'] ?? time())) - time());

        Cache::store(config('jwt.blacklist_store', 'redis'))->put(
            $this->blacklistKey($token),
            true,
            $secondsUntilExpiry
        );
    }

    public function isBlacklisted(string $token): bool
    {
        return (bool) Cache::store(config('jwt.blacklist_store', 'redis'))->get(
            $this->blacklistKey($token)
        );
    }

    private function decodeIgnoringBlacklist(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format.');
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;
        $expectedSignature = $this->sign("{$encodedHeader}.{$encodedPayload}");

        if (! hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Invalid token signature.');
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid token payload.');
        }

        return $payload;
    }

    private function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign("{$encodedHeader}.{$encodedPayload}");

        return "{$encodedHeader}.{$encodedPayload}.{$signature}";
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret(), true));
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if ($secret === '') {
            throw new RuntimeException('JWT secret is not configured.');
        }

        return $secret;
    }

    private function blacklistKey(string $token): string
    {
        return 'auth:blacklist:' . hash('sha256', $token);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}

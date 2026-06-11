<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class JwtGuard implements Guard
{
    protected ?JwtUser $user = null;
    protected Request  $request;
    protected string   $secret;

    public function __construct(Request $request, string $secret)
    {
        $this->request = $request;
        $this->secret  = $secret;
    }

    public function check(): bool   { return $this->user() !== null; }
    public function guest(): bool   { return !$this->check(); }
    public function hasUser(): bool { return $this->user !== null; }

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getBearerToken();
        if (!$token) {
            return null;
        }

        $payload = $this->decodeToken($token);
        if (!$payload) {
            return null;
        }

        // Soporta tanto nombres cortos (sub/email/name) como URIs largas de ClaimTypes
        $sub = $payload->sub
            ?? $payload->{'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/nameidentifier'}
            ?? null;

        $email = $payload->email
            ?? $payload->{'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'}
            ?? '';

        $name = $payload->name
            ?? $payload->{'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name'}
            ?? '';

        if (!$sub) {
            return null;
        }

        // Múltiples claims "permission" se serializan como array en el JWT
        $permissions = [];
        if (isset($payload->permission)) {
            $raw = $payload->permission;
            $permissions = is_array($raw) ? $raw : [$raw];
        }

        $this->user = new JwtUser((int) $sub, $email, $name, $permissions);

        return $this->user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;
        return $this;
    }

    // -------------------------------------------------------------------------

    private function getBearerToken(): ?string
    {
        $header = (string) $this->request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    /**
     * Decodifica y verifica un JWT HS256 sin dependencias externas.
     */
    private function decodeToken(string $token): ?object
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // Verificar firma HMAC-SHA256
        $signingInput  = "$header.$payload";
        $expectedSig   = hash_hmac('sha256', $signingInput, $this->secret, true);
        $providedSig   = base64_decode(strtr($signature, '-_', '+/') . str_repeat('=', 3));

        if (!hash_equals($expectedSig, $providedSig)) {
            return null;
        }

        // Decodificar payload
        $payloadJson = base64_decode(strtr($payload, '-_', '+/') . str_repeat('=', 3));
        $decoded     = json_decode($payloadJson);

        if (!$decoded) {
            return null;
        }

        // Verificar expiración
        if (isset($decoded->exp) && $decoded->exp < time()) {
            return null;
        }

        return $decoded;
    }
}

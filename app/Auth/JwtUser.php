<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class JwtUser implements Authenticatable
{
    public int    $id;
    public string $correo;
    public string $email;
    public string $nombre;
    public string $name;
    public array  $permissions;

    public function __construct(int $id, string $correo, string $nombre, array $permissions)
    {
        $this->id          = $id;
        $this->correo      = $correo;
        $this->email       = $correo;
        $this->nombre      = $nombre;
        $this->name        = $nombre;
        $this->permissions = $permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthIdentifier(): mixed      { return $this->id; }
    public function getAuthPasswordName(): string   { return 'password'; }
    public function getAuthPassword(): string       { return ''; }
    public function getRememberToken(): ?string     { return null; }
    public function setRememberToken($value): void  {}
    public function getRememberTokenName(): string  { return ''; }
}

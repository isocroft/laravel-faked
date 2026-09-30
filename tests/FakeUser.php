<?php

namespace LaravelFaked\Tests;

use LaravelFaked\DataSource\FakeModel;

/** Test double for App\Models\User (Authenticatable + the package's tenant/active-column API). */
class FakeUser extends FakeModel
{
    protected ?string $table = 'users';
    protected array $fillable = ['name', 'email', 'password', 'is_active'];
    protected array $hidden = ['password', 'remember_token'];
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];
    protected array $attributes = [];
 
    /* ---- Illuminate\Contracts\Auth\Authenticatable ----------------------- */
 
    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }
 
    public function getAuthIdentifier(): mixed
    {
        return $this->getAttribute($this->getAuthIdentifierName());
    }
 
    public function getAuthPasswordName(): string
    {
        return 'password';
    }
 
    public function getAuthPassword(): ?string
    {
        return $this->attributes[$this->getAuthPasswordName()] ?? null;
    }
 
    public function getRememberToken(): ?string
    {
        return $this->getAttribute($this->getRememberTokenName());
    }
 
    public function setRememberToken(string $value): void
    {
        $this->setAttribute($this->getRememberTokenName(), $value);
    }
 
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
 
    /* ---- MustVerifyEmail-ish -------------------------------------------- */
 
    public function hasVerifiedEmail(): bool
    {
        return $this->getAttribute('email_verified_at') !== null;
    }
 
    public function markEmailAsVerified(): bool
    {
        $this->setAttribute('email_verified_at', new \DateTimeImmutable());
 
        return $this->save();
    }
 
    /* ---- package-specific API (from the original stubs) ------------------ */
 
    public function getTableName(): string
    {
        return $this->getTable();
    }
 
    public function getUserActiveColumnName(): string
    {
        return 'is_active';
    }
 
    public function getUserActiveColumnType(): string
    {
        return 'bool';
    }
 
    public function isActive(): bool
    {
        return (bool) $this->getAttribute($this->getUserActiveColumnName());
    }
}

?>

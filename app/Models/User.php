<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasRole(array|string $roles): bool
    {
        $roles = collect(is_array($roles) ? $roles : [$roles])
            ->map(fn (string $role) => strtolower(trim($role)))
            ->all();

        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->hasRole('ppc');
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'ppc' => 'PPC',
            default => 'PPC',
        };
    }

    public function getRoleAttribute(?string $value): string
    {
        return strtolower(trim($value ?: 'ppc'));
    }

    public function setRoleAttribute(?string $value): void
    {
        $this->attributes['role'] = strtolower(trim($value ?: 'ppc'));
    }
}

<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A staff account inside one tenant. Emails are unique per tenant, not
 * globally, so the same person can hold accounts at several businesses.
 *
 * @property int $tenant_id
 * @property UserRole $role
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable. `tenant_id` and `role` are
     * deliberately absent: both are set by the application, never from input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Member->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }
}

<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'password', 'role', 'status', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_OWNER = 'OWNER';

    public const ROLE_CASHIER = 'CASHIER';

    public const ROLES = [self::ROLE_OWNER, self::ROLE_CASHIER];

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime', // only present when loaded via withMax()
        ];
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(UserLoginLog::class);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isCashier(): bool
    {
        return $this->role === self::ROLE_CASHIER;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Does this account have any history that must be preserved?
     * Staff with history are deactivated, never hard-deleted.
     *
     * TODO (Module 4): also return true when the user has recorded transactions,
     * e.g. `|| $this->transactions()->exists()`.
     */
    public function hasHistory(): bool
    {
        return $this->loginLogs()->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOwners(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_OWNER);
    }
}

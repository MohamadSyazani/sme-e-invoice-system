<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['username', 'user_id', 'reason', 'ip_address', 'user_agent', 'attempted_at'])]
class FailedLoginAttempt extends Model
{
    public const REASON_UNKNOWN_USERNAME = 'UNKNOWN_USERNAME';

    public const REASON_WRONG_PASSWORD = 'WRONG_PASSWORD';

    public const REASON_INACTIVE_ACCOUNT = 'INACTIVE_ACCOUNT';

    public const REASONS = [
        self::REASON_UNKNOWN_USERNAME,
        self::REASON_WRONG_PASSWORD,
        self::REASON_INACTIVE_ACCOUNT,
    ];

    /** The table only has attempted_at; there is no created_at / updated_at. */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Plain-English reason shown in the report. */
    protected function reasonLabel(): Attribute
    {
        return Attribute::get(fn (): string => match ($this->reason) {
            self::REASON_UNKNOWN_USERNAME => 'Username not registered',
            self::REASON_WRONG_PASSWORD => 'Wrong password',
            self::REASON_INACTIVE_ACCOUNT => 'Inactive account',
            default => $this->reason,
        });
    }

    /** Same browser parsing as UserLoginLog, so both reports read alike. */
    protected function browser(): Attribute
    {
        return Attribute::get(fn (): string => (new UserLoginLog(['user_agent' => $this->user_agent]))->browser);
    }

    protected function platform(): Attribute
    {
        return Attribute::get(fn (): string => (new UserLoginLog(['user_agent' => $this->user_agent]))->platform);
    }
}

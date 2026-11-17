<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'login_at', 'ip_address', 'user_agent'])]
class UserLoginLog extends Model
{
    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Human-readable browser name parsed from the raw user agent. */
    protected function browser(): Attribute
    {
        return Attribute::get(function (): string {
            $ua = (string) $this->user_agent;

            return match (true) {
                $ua === '' => 'Unknown',
                str_contains($ua, 'Edg') => 'Microsoft Edge',
                str_contains($ua, 'OPR'), str_contains($ua, 'Opera') => 'Opera',
                str_contains($ua, 'Firefox') => 'Firefox',
                str_contains($ua, 'Chrome') => 'Chrome',
                str_contains($ua, 'Safari') => 'Safari',
                default => 'Other',
            };
        });
    }

    /** Human-readable operating system parsed from the raw user agent. */
    protected function platform(): Attribute
    {
        return Attribute::get(function (): string {
            $ua = (string) $this->user_agent;

            return match (true) {
                $ua === '' => 'Unknown',
                str_contains($ua, 'Android') => 'Android',
                str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
                str_contains($ua, 'Windows') => 'Windows',
                str_contains($ua, 'Mac OS') => 'macOS',
                str_contains($ua, 'Linux') => 'Linux',
                default => 'Other',
            };
        });
    }
}

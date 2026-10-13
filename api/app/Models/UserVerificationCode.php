<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVerificationCode extends Model
{
    use HasFactory;

    /**
     * Minutes a code stays valid.
     */
    public const LIFETIME_MINUTES = 10;

    protected $fillable = [
        'session_id',
        'code',
        'method',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The code with a space between each 3 digit section (e.g. "123 456").
     */
    public function getFormattedCodeAttribute(): string
    {
        return wordwrap($this->code, 3, ' ', true);
    }

    public function isNotExpired(): bool
    {
        return $this->created_at->gt(now()->subMinutes(self::LIFETIME_MINUTES));
    }

    public function scopeViaMethod($query, string $method): void
    {
        $query->where('method', $method);
    }

    public function isEmail(): bool
    {
        return $this->method === 'email';
    }
}

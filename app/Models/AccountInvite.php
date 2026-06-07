<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccountInvite extends Model
{
    protected $fillable = [
        'token', 'grant_days', 'expires_at', 'used_at', 'used_by', 'created_by', 'note',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'grant_days' => 'integer',
        ];
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public static function generate(int $grantDays = 365, ?int $validDays = 30, ?int $createdBy = null): self
    {
        return static::create([
            'token' => Str::random(40),
            'grant_days' => $grantDays,
            'expires_at' => $validDays ? now()->addDays($validDays) : null,
            'created_by' => $createdBy,
        ]);
    }

    /** ¿Sigue siendo canjeable? (no usado y no caducado). */
    public function isUsable(): bool
    {
        return $this->used_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function url(): string
    {
        return route('invite.show', $this->token);
    }
}

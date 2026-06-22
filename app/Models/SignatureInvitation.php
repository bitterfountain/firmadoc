<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureInvitation extends Model
{
    protected $fillable = [
        'document_id', 'name', 'email', 'token', 'position',
        'status', 'signed_at', 'expires_at', 'declined_at', 'last_reminded_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
        'declined_at' => 'datetime',
        'last_reminded_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * ¿Le toca firmar?
     * - Si el documento es paralelo: todos los pendientes pueden firmar en cualquier orden.
     * - Si es secuencial: solo si todas las posiciones anteriores ya han firmado.
     */
    public function isMyTurn(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if (! $this->document->isSequential()) {
            return true;
        }

        return ! $this->document->invitations()
            ->where('position', '<', $this->position)
            ->where('status', '!=', 'signed')
            ->exists();
    }
}

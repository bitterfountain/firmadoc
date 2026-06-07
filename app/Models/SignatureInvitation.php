<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureInvitation extends Model
{
    protected $fillable = [
        'document_id', 'name', 'email', 'token', 'position', 'status', 'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * ¿Le toca firmar? (firma secuencial: todas las posiciones anteriores ya firmadas).
     */
    public function isMyTurn(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return ! $this->document->invitations()
            ->where('position', '<', $this->position)
            ->where('status', '!=', 'signed')
            ->exists();
    }
}

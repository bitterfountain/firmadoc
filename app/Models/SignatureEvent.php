<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureEvent extends Model
{
    protected $fillable = [
        'document_id', 'invitation_id', 'signer_name', 'signer_email', 'ip_address', 'user_agent',
        'otp_hash', 'otp_expires_at', 'attempts', 'verified_at',
        'original_sha256', 'signed_sha256', 'pades_applied', 'status',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    protected $hidden = ['otp_hash'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isOtpValid(): bool
    {
        return $this->status === 'pending' && $this->otp_expires_at->isFuture();
    }

    /** Referencia legible y unica del evento de firma (p.ej. DS-00007-A1B2C3). */
    public function getReferenceAttribute(): string
    {
        return 'DS-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT)
            . '-' . strtoupper(substr(sha1($this->id . $this->created_at), 0, 6));
    }
}

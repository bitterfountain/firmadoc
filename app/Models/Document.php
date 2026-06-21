<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signatureEvents(): HasMany
    {
        return $this->hasMany(SignatureEvent::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(SignatureInvitation::class)->orderBy('position');
    }

    protected $fillable = [
        'user_id',
        'original_name',
        'source_format',
        'pdf_path',
        'signed_path',
        'status',
        'error',
        'signing_mode',
        'witness_name',
        'witness_email',
        'witness_token',
        'witness_confirmed_at',
        'webhook_url',
    ];

    protected $casts = [
        'witness_confirmed_at' => 'datetime',
    ];

    /** Formatos de imagen que convertimos a PDF. */
    public const IMAGE_FORMATS = ['jpg', 'jpeg', 'png'];

    /** Formatos que requieren LibreOffice para convertirse. */
    public const OFFICE_FORMATS = ['docx', 'doc', 'odt'];

    public function isReadyToSign(): bool
    {
        return $this->status === 'ready' && $this->pdf_path !== null;
    }

    public function isSequential(): bool
    {
        return $this->signing_mode !== 'parallel';
    }

    public function allSigned(): bool
    {
        return ! $this->invitations()->where('status', '!=', 'signed')->exists();
    }

    public function pendingInvitations()
    {
        return $this->invitations()->where('status', 'pending');
    }
}

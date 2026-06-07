<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    public function signatureEvents(): HasMany
    {
        return $this->hasMany(SignatureEvent::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(SignatureInvitation::class)->orderBy('position');
    }

    protected $fillable = [
        'original_name',
        'source_format',
        'pdf_path',
        'signed_path',
        'status',
        'error',
    ];

    /** Formatos de imagen que convertimos a PDF. */
    public const IMAGE_FORMATS = ['jpg', 'jpeg', 'png'];

    /** Formatos que requieren LibreOffice para convertirse. */
    public const OFFICE_FORMATS = ['docx', 'doc', 'odt'];

    public function isReadyToSign(): bool
    {
        return $this->status === 'ready' && $this->pdf_path !== null;
    }
}

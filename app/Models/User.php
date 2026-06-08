<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'pro_until',
        'is_admin',
        'signing_cert',
        'signing_cert_password',
        'signing_cert_subject',
        'signing_cert_name',
        'signing_cert_expires_at',
    ];

    /** Cuenta profesional activa: sin caducidad (NULL) o aún vigente. */
    public function proActive(): bool
    {
        return $this->pro_until === null || $this->pro_until->isFuture();
    }

    /** ¿El usuario ha subido un certificado propio de firma? */
    public function hasSigningCert(): bool
    {
        return ! empty($this->signing_cert);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pro_until' => 'datetime',
            'is_admin' => 'boolean',
            'signing_cert' => 'encrypted',
            'signing_cert_password' => 'encrypted',
            'signing_cert_expires_at' => 'date',
        ];
    }
}

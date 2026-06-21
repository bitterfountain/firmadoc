<?php

namespace App\Console\Commands;

use App\Models\AccountInvite;
use Illuminate\Console\Command;

/**
 * Genera un enlace de invitación de un solo uso para una cuenta profesional gratis.
 *   php artisan firmadoc:invite            (1 año de Pro, enlace válido 30 días)
 *   php artisan firmadoc:invite --days=365 --valid=60
 */
class MakeInvite extends Command
{
    protected $signature = 'firmadoc:invite {--days=365 : Dias de cuenta Pro que concede} {--valid=30 : Dias que el enlace puede canjearse}';

    protected $description = 'Crea un enlace de invitación de un solo uso (cuenta profesional gratis)';

    public function handle(): int
    {
        $invite = AccountInvite::generate(
            (int) $this->option('days'),
            (int) $this->option('valid') ?: null,
        );

        $this->info('Enlace de invitación creado:');
        $this->line($invite->url());
        $this->line("Concede: {$invite->grant_days} días de Pro · Caduca el enlace: "
            .($invite->expires_at?->format('d/m/Y') ?? 'nunca'));

        return self::SUCCESS;
    }
}

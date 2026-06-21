<?php

namespace App\Console\Commands;

use App\Mail\SignatureReminderMail;
use App\Models\SignatureInvitation;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSignatureReminders extends Command
{
    protected $signature = 'firmadoc:send-reminders {--days=3 : Recordar a quienes no han firmado en estos dias}';

    protected $description = 'Envia recordatorios por email a los firmantes pendientes';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $invitations = SignatureInvitation::with('document')
            ->where('status', 'pending')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<', $cutoff);
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        $count = 0;

        foreach ($invitations as $invitation) {
            if (! $invitation->isMyTurn()) {
                continue;
            }

            $expiresIn = $invitation->expires_at
                ? $invitation->expires_at->diffForHumans(now(), ['syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW])
                : null;

            try {
                Mail::to($invitation->email)->send(new SignatureReminderMail(
                    $invitation->name,
                    $invitation->document->original_name,
                    route('sign.show', $invitation->token),
                    $expiresIn ?? '',
                ));

                $invitation->update(['last_reminded_at' => now()]);
                $count++;

                $this->line("Recordatorio enviado a: {$invitation->email} ({$invitation->name})");
            } catch (Throwable $e) {
                $this->error("Fallo al enviar a {$invitation->email}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("{$count} recordatorio(s) enviado(s).");

        return self::SUCCESS;
    }
}

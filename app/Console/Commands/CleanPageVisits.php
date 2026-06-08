<?php

namespace App\Console\Commands;

use App\Models\PageVisit;
use Illuminate\Console\Command;

/** Borra visitas más antiguas que N días (retención). */
class CleanPageVisits extends Command
{
    protected $signature = 'firmadoc:clean-visits {--days=180}';

    protected $description = 'Elimina registros de page_visits anteriores a N días';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = PageVisit::where('visited_at', '<', now()->subDays($days))->delete();

        $this->info("Visitas eliminadas (> {$days} días): {$deleted}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\PageVisit;
use App\Models\SignatureEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitAnalyticsController extends Controller
{
    /** Panel de visitas — solo administradores. */
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $days = (int) $request->get('days', 30);
        if (! in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }
        $since = now()->subDays($days);
        $q = PageVisit::since($since);

        $total = (clone $q)->count();
        $unique = (clone $q)->distinct('ip')->count('ip');

        $byType = (clone $q)->selectRaw('page_type, COUNT(*) as c')
            ->groupBy('page_type')->orderByDesc('c')->pluck('c', 'page_type')->toArray();

        $topCountries = (clone $q)->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) as c')
            ->groupBy('country_code')->orderByDesc('c')->limit(12)
            ->pluck('c', 'country_code')->toArray();

        // Por día y por hora: en PHP, portable entre SQLite/MySQL.
        $times = (clone $q)->get(['visited_at']);
        $byDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $byDay[now()->subDays($i)->format('Y-m-d')] = 0;
        }
        $byHour = array_fill(0, 24, 0);
        foreach ($times as $t) {
            $d = $t->visited_at->format('Y-m-d');
            if (isset($byDay[$d])) {
                $byDay[$d]++;
            }
            $byHour[(int) $t->visited_at->format('G')]++;
        }

        // Estadisticas de firma
        $signedDocs = Document::where('status', 'signed')->count();
        $anonSignatures = SignatureEvent::whereNotNull('invitation_id')
            ->where('status', 'completed')->count();
        $registeredSignatures = SignatureEvent::whereNull('invitation_id')
            ->where('status', 'completed')->count();

        return view('admin.visits', compact(
            'days', 'total', 'unique', 'byType', 'topCountries', 'byDay', 'byHour',
            'signedDocs', 'anonSignatures', 'registeredSignatures'
        ));
    }
}

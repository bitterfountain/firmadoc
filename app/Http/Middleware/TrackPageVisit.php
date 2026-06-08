<?php

namespace App\Http\Middleware;

use App\Models\PageVisit;
use App\Services\GeoIpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackPageVisit
{
    public function __construct(private GeoIpService $geoIp) {}

    public function handle(Request $request, Closure $next, string $pageType): Response
    {
        $response = $next($request);

        // Solo GET correctos; excluimos bots.
        if (! $request->isMethod('GET') || $response->getStatusCode() >= 400) {
            return $response;
        }

        $ua = $request->userAgent() ?? '';
        if ($this->isBot($ua)) {
            return $response;
        }

        try {
            $ip = $request->ip();
            $geo = $this->geoIp->lookup((string) $ip);

            PageVisit::create([
                'ip' => $ip,
                'country_code' => $geo['country_code'],
                'city' => $geo['city'],
                'url' => mb_substr($request->fullUrl(), 0, 500),
                'page_type' => $pageType,
                'user_agent' => mb_substr($ua, 0, 500) ?: null,
                'referer' => mb_substr((string) $request->header('referer', ''), 0, 500) ?: null,
                'visited_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e); // nunca rompemos la página por el tracking
        }

        return $response;
    }

    private function isBot(string $ua): bool
    {
        $ua = strtolower($ua);
        foreach (['bot', 'crawl', 'spider', 'slurp', 'lighthouse', 'pingdom', 'uptimerobot', 'headlesschrome'] as $bot) {
            if (str_contains($ua, $bot)) {
                return true;
            }
        }

        return false;
    }
}

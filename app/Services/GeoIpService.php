<?php

namespace App\Services;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve país/ciudad de una IP con la base GeoLite2 de MaxMind.
 * Degrada con elegancia: si no hay BD o el paquete, devuelve nulls.
 */
class GeoIpService
{
    private ?Reader $reader = null;

    public function lookup(string $ip): array
    {
        $result = ['country_code' => null, 'city' => null];

        if (! config('geoip.enabled') || $this->isPrivateIp($ip)) {
            return $result;
        }

        try {
            $reader = $this->getReader();
            if (! $reader) {
                return $result;
            }

            $record = $reader->city($ip);
            $result['country_code'] = $record->country->isoCode;
            $result['city'] = $record->city->name;
        } catch (AddressNotFoundException) {
            // IP no encontrada en la base.
        } catch (\Throwable $e) {
            Log::warning('GeoIP lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    private function getReader(): ?Reader
    {
        if ($this->reader) {
            return $this->reader;
        }

        $path = config('geoip.database_path');
        if (! $path || ! file_exists($path) || ! class_exists(Reader::class)) {
            return null;
        }

        return $this->reader = new Reader($path);
    }

    private function isPrivateIp(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    public function __destruct()
    {
        $this->reader?->close();
    }
}

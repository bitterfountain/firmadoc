<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpSmsService
{
    private string $endpoint;
    private string $apiKey;

    public function __construct()
    {
        $this->endpoint = config('services.httpsms.endpoint', 'https://httpsms.leukasoft.com/api');
        $this->apiKey = config('services.httpsms.api_key', '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function send(string $to, string $content, string $from = 'Docsigner', ?string $scheduledAt = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'httpSMS no configurado'];
        }

        try {
            $payload = [
                'from' => $from,
                'to' => $to,
                'content' => $content,
            ];

            if ($scheduledAt) {
                $payload['scheduled_at'] = $scheduledAt;
            }

            $response = Http::timeout(10)
                ->withToken($this->apiKey)
                ->post($this->endpoint . '/v1/messages/send', $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("httpSMS: mensaje enviado", ['id' => $data['id'] ?? null, 'to' => $to]);
                return ['success' => true, 'id' => $data['id'] ?? null, 'status' => $data['status'] ?? 'unknown'];
            }

            if ($response->status() === 429) {
                Log::warning("httpSMS: rate limit alcanzado", ['to' => $to]);
                return ['success' => false, 'error' => 'Rate limit alcanzado, reintente en unos segundos'];
            }

            if ($response->status() === 409) {
                Log::warning("httpSMS: sin telefono online", ['to' => $to]);
                return ['success' => false, 'error' => 'No hay ningun telefono online para enviar SMS'];
            }

            Log::error("httpSMS: error al enviar", [
                'status' => $response->status(),
                'body' => $response->body(),
                'to' => $to,
            ]);

            return ['success' => false, 'error' => 'Error del servidor SMS: HTTP ' . $response->status()];
        } catch (\Throwable $e) {
            Log::error("httpSMS: excepcion", [
                'message' => $e->getMessage(),
                'to' => $to,
            ]);
            return ['success' => false, 'error' => 'Error de conexion con el servicio SMS'];
        }
    }

    public function getStatus(int $messageId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'httpSMS no configurado'];
        }

        try {
            $response = Http::timeout(5)
                ->withToken($this->apiKey)
                ->get($this->endpoint . '/v1/messages/' . $messageId);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'error' => 'Mensaje no encontrado'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error de conexion'];
        }
    }

    public function listMessages(int $page = 1, int $perPage = 20, ?string $status = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'httpSMS no configurado'];
        }

        try {
            $query = ['page' => $page, 'per_page' => $perPage];
            if ($status) {
                $query['status'] = $status;
            }

            $response = Http::timeout(5)
                ->withToken($this->apiKey)
                ->get($this->endpoint . '/v1/messages', $query);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'error' => 'Error al listar mensajes'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error de conexion'];
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\HttpSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function send(Request $request, HttpSmsService $sms): JsonResponse
    {
        $request->validate([
            'to' => ['required', 'string', 'max:20'],
            'content' => ['required', 'string', 'max:1600'],
            'from' => ['nullable', 'string', 'max:20'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        $result = $sms->send(
            to: $request->to,
            content: $request->content,
            from: $request->from ?? 'Docsigner',
            scheduledAt: $request->scheduled_at,
        );

        if ($result['success']) {
            return response()->json($result, 201);
        }

        return response()->json($result, 502);
    }

    public function status(int $id, HttpSmsService $sms): JsonResponse
    {
        $result = $sms->getStatus($id);

        if ($result['success']) {
            return response()->json($result['data']);
        }

        return response()->json($result, 404);
    }

    public function index(Request $request, HttpSmsService $sms): JsonResponse
    {
        $result = $sms->listMessages(
            page: $request->integer('page', 1),
            perPage: min($request->integer('per_page', 20), 100),
            status: $request->get('status'),
        );

        if ($result['success']) {
            return response()->json($result['data']);
        }

        return response()->json($result, 502);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FormulaDcWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $event = (string) $request->input('event', '');
        $data = $request->input('data', []);

        Log::info('Formula DC webhook', [
            'event' => $event,
            'message_id' => is_array($data) ? ($data['messageId'] ?? $data['message_id'] ?? null) : null,
            'recipient' => is_array($data) ? ($data['recipient'] ?? null) : null,
            'status' => is_array($data) ? ($data['status'] ?? null) : null,
            'batch_id' => is_array($data) ? ($data['batchId'] ?? $data['batch_id'] ?? null) : null,
        ]);

        return response('OK', 200);
    }
}

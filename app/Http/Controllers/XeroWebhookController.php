<?php

namespace App\Http\Controllers;

use App\Models\XeroSettings;
use App\Services\XeroService;
use App\Support\SafeLog;
use App\Support\XeroWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XeroWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $webhookKey = config('services.xero.webhook_key');
        $signature = $request->header('x-xero-signature');

        if (! is_string($webhookKey) || $webhookKey === '') {
            SafeLog::integration('warning', 'xero', 'Webhook rejected: XERO_WEBHOOK_KEY is not configured');

            return response()->json(['error' => 'Webhook not configured'], 503);
        }

        if (! XeroWebhookSignature::isValid($rawBody, $signature, $webhookKey)) {
            SafeLog::integration('warning', 'xero', 'Webhook rejected: invalid or missing signature');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            return response()->json(['error' => 'Invalid JSON payload'], 400);
        }

        $events = $data['events'] ?? [];
        if (! is_array($events)) {
            $events = [];
        }

        $tenantId = $data['tenantId'] ?? $data['tenant_id'] ?? null;
        if ($tenantId === null && $events !== []) {
            $first = reset($events);
            if (is_array($first)) {
                $tenantId = $first['tenantId'] ?? $first['tenant_id'] ?? null;
            }
        }

        $settings = null;
        if ($tenantId !== null && $tenantId !== '') {
            $settings = XeroSettings::where('tenant_id', $tenantId)->first();
        }

        if (! $settings) {
            SafeLog::integration('info', 'xero', 'Webhook ignored: no local settings for tenant', [
                'tenant_id' => $tenantId,
            ]);

            return response()->json(['status' => 'ignored']);
        }

        $xeroService = new XeroService($settings->company);
        $xeroService->handleInvoiceWebhook($events);
        $xeroService->handleCreditNoteWebhook($events);

        return response()->json(['status' => 'success']);
    }
}

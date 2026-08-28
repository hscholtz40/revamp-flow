<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\GoogleIntegrationSettings;
use App\Models\Invoice;
use App\Models\Jobcard;
use App\Models\PdfTemplate;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\AiAccessService;
use App\Services\AI\AiAuditService;
use App\Services\AI\OpenAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiController extends Controller
{
    public function __construct(
        private readonly AiAccessService $access,
        private readonly OpenAiClient $openAi,
        private readonly AiAuditService $audit,
    ) {}

    public function draft(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $this->access->ensureFeatureAllowed($user, 'draft');

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:5000'],
            'context' => ['nullable', 'string', 'max:5000'],
            'tone' => ['nullable', 'string', 'max:50'],
            'length' => ['nullable', 'in:short,medium,long'],
        ]);

        $traceId = (string) Str::uuid();
        $start = microtime(true);
        $resultText = '';
        $statusCode = 200;
        $usage = ['model' => config('services.openai.model', 'gpt-4o-mini')];

        try {
            $response = $this->openAi->chatJson([
                ['role' => 'system', 'content' => 'You are a concise business writing assistant.'],
                ['role' => 'user', 'content' => "Tone: ".($validated['tone'] ?? 'professional')."\nLength: ".($validated['length'] ?? 'medium')."\nContext: ".($validated['context'] ?? '')."\nTask: ".$validated['prompt']],
            ]);
            $resultText = (string) data_get($response, 'choices.0.message.content', '');
            $usage = [
                'model' => (string) data_get($response, 'model', config('services.openai.model', 'gpt-4o-mini')),
                'prompt_tokens' => (int) data_get($response, 'usage.prompt_tokens', 0),
                'completion_tokens' => (int) data_get($response, 'usage.completion_tokens', 0),
                'total_tokens' => (int) data_get($response, 'usage.total_tokens', 0),
            ];
        } catch (\Throwable $e) {
            $statusCode = 422;
            $resultText = trim(($validated['context'] ?? '')."\n".$validated['prompt']);
        }

        if ($resultText === '') {
            $resultText = 'No draft result returned.';
        }

        $latency = (int) round((microtime(true) - $start) * 1000);
        if (GoogleIntegrationSettings::record()->ai_prompt_logging_enabled) {
            $this->audit->record($user, 'draft', $usage, $latency, $statusCode, $traceId, $validated['prompt'], $resultText);
        }

        return response()->json([
            'trace_id' => $traceId,
            'text' => $resultText,
        ], $statusCode === 200 ? 200 : 200);
    }

    public function suggestTechnician(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $this->access->ensureFeatureAllowed($user, 'dispatch_technician');
        $validated = $request->validate([
            'jobcard_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'service_address' => ['nullable', 'string', 'max:500'],
        ]);

        $companyId = $user->getCurrentCompany()?->id;
        $technicians = User::query()
            ->staffSelectableForCompany((int) $companyId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $scores = [];
        foreach ($technicians as $tech) {
            $openJobs = Jobcard::query()
                ->where('company_id', $companyId)
                ->where('assigned_to_user_id', $tech->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
            $score = max(1, 100 - ($openJobs * 12));
            $scores[] = [
                'user_id' => (int) $tech->id,
                'name' => (string) $tech->name,
                'score' => $score,
                'reason' => $openJobs === 0 ? 'No active assigned jobs.' : "Current active jobs: {$openJobs}",
            ];
        }

        usort($scores, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $traceId = (string) Str::uuid();
        if (GoogleIntegrationSettings::record()->ai_prompt_logging_enabled) {
            $this->audit->record($user, 'dispatch_technician', ['model' => 'heuristic'], 0, 200, $traceId, json_encode($validated), json_encode($scores));
        }

        return response()->json([
            'trace_id' => $traceId,
            'suggestions' => array_slice($scores, 0, 5),
        ]);
    }

    public function suggestDocument(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $this->access->ensureFeatureAllowed($user, 'document_suggestion');
        $validated = $request->validate([
            'document_type' => ['required', 'in:quote,invoice,jobcard'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $companyId = $user->getCurrentCompany()?->id;
        $pdfTemplates = PdfTemplate::query()
            ->where('company_id', $companyId)
            ->where('module', $validated['document_type'])
            ->where('is_active', true)
            ->get(['id', 'name', 'is_default']);
        $emailTemplates = EmailTemplate::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->limit(10)
            ->get(['id', 'name', 'subject', 'is_default']);

        $pdfSuggestion = $pdfTemplates->sortByDesc('is_default')->first();
        $emailSuggestion = $emailTemplates->sortByDesc('is_default')->first();

        $result = [
            'document_type' => $validated['document_type'],
            'pdf_template' => $pdfSuggestion ? [
                'id' => (int) $pdfSuggestion->id,
                'name' => (string) $pdfSuggestion->name,
                'reason' => $pdfSuggestion->is_default ? 'Default active template.' : 'Active template match.',
            ] : null,
            'email_template' => $emailSuggestion ? [
                'id' => (int) $emailSuggestion->id,
                'name' => (string) $emailSuggestion->name,
                'reason' => $emailSuggestion->is_default ? 'Default active email template.' : 'Recently active template.',
            ] : null,
            'rationale' => 'Based on active defaults for your company and document type.',
        ];

        $traceId = (string) Str::uuid();
        if (GoogleIntegrationSettings::record()->ai_prompt_logging_enabled) {
            $this->audit->record($user, 'document_suggestion', ['model' => 'heuristic'], 0, 200, $traceId, json_encode($validated), json_encode($result));
        }

        return response()->json([
            'trace_id' => $traceId,
            'suggestion' => $result,
        ]);
    }

    public function assistant(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $this->access->ensureFeatureAllowed($user, 'assistant');
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:500'],
        ]);

        $companyId = $user->getCurrentCompany()?->id;
        $q = trim($validated['query']);

        $results = [
            'jobcards' => Jobcard::query()->where('company_id', $companyId)
                ->where(function ($w) use ($q) {
                    $w->where('job_number', 'like', "%{$q}%")
                        ->orWhere('title', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                })
                ->limit(5)->get(['id', 'job_number', 'title', 'status']),
            'quotes' => Quote::query()->where('company_id', $companyId)
                ->where(function ($w) use ($q) {
                    $w->where('quote_number', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%");
                })
                ->limit(5)->get(['id', 'quote_number', 'status']),
            'invoices' => Invoice::query()->where('company_id', $companyId)
                ->where(function ($w) use ($q) {
                    $w->where('invoice_number', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%");
                })
                ->limit(5)->get(['id', 'invoice_number', 'status']),
            'tasks' => Task::query()->where('company_id', $companyId)
                ->where(function ($w) use ($q) {
                    $w->where('title', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%");
                })
                ->limit(5)->get(['id', 'title', 'status']),
            'customers' => Customer::query()->where('company_id', $companyId)
                ->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
                })
                ->limit(5)->get(['id', 'name', 'email']),
            'contacts' => Contact::query()->where('company_id', $companyId)
                ->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
                })
                ->limit(5)->get(['id', 'name', 'email']),
        ];

        $summary = "Found {$results['jobcards']->count()} jobcards, {$results['quotes']->count()} quotes, {$results['invoices']->count()} invoices, {$results['tasks']->count()} tasks, {$results['customers']->count()} customers, and {$results['contacts']->count()} contacts.";
        $traceId = (string) Str::uuid();
        if (GoogleIntegrationSettings::record()->ai_prompt_logging_enabled) {
            $this->audit->record($user, 'assistant', ['model' => 'readonly-query'], 0, 200, $traceId, $q, $summary);
        }

        return response()->json([
            'trace_id' => $traceId,
            'summary' => $summary,
            'results' => $results,
        ]);
    }

    public function transcribe(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $this->access->ensureFeatureAllowed($user, 'assistant');

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:25600', 'mimetypes:audio/webm,audio/wav,audio/mpeg,audio/mp4,audio/ogg,audio/x-wav,video/webm'],
        ]);

        $file = $validated['audio'];
        $traceId = (string) Str::uuid();
        $start = microtime(true);
        $statusCode = 200;
        $text = '';

        try {
            $text = $this->openAi->transcribe(
                $file->getRealPath(),
                $file->getClientOriginalName() ?: ('voice-note.'.$file->getClientOriginalExtension())
            );
        } catch (\Throwable $e) {
            $statusCode = 422;
            $latency = (int) round((microtime(true) - $start) * 1000);
            if (GoogleIntegrationSettings::record()->ai_prompt_logging_enabled) {
                $this->audit->record($user, 'transcribe', ['model' => config('services.openai.transcription_model', 'gpt-4o-mini-transcribe')], $latency, $statusCode, $traceId, '[voice-note]', $e->getMessage());
            }

            return response()->json([
                'trace_id' => $traceId,
                'message' => $e->getMessage() ?: 'Could not transcribe the voice note.',
            ], 422);
        }

        $latency = (int) round((microtime(true) - $start) * 1000);
        if (GoogleIntegrationSettings::record()->ai_prompt_logging_enabled) {
            $this->audit->record($user, 'transcribe', ['model' => config('services.openai.transcription_model', 'gpt-4o-mini-transcribe')], $latency, $statusCode, $traceId, '[voice-note]', $text);
        }

        return response()->json([
            'trace_id' => $traceId,
            'text' => $text,
        ]);
    }
}

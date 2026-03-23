<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Jobcard;
use App\Models\License;
use App\Models\Note;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductSerialNumber;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Report;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class NotesController extends Controller
{
    /**
     * @return array<string, array{class: class-string<Model>, label: string}>
     */
    private function moduleMap(): array
    {
        return [
            'customers' => ['class' => Customer::class, 'label' => 'Customers'],
            'contacts' => ['class' => Contact::class, 'label' => 'Contacts'],
            'products' => ['class' => Product::class, 'label' => 'Products'],
            'product-batches' => ['class' => ProductBatch::class, 'label' => 'Product Batches'],
            'product-serial-numbers' => ['class' => ProductSerialNumber::class, 'label' => 'Product Serial Numbers'],
            'suppliers' => ['class' => Supplier::class, 'label' => 'Suppliers'],
            'stock-movements' => ['class' => StockMovement::class, 'label' => 'Stock Movements'],
            'jobcards' => ['class' => Jobcard::class, 'label' => 'Jobcards'],
            'quotes' => ['class' => Quote::class, 'label' => 'Quotes'],
            'invoices' => ['class' => Invoice::class, 'label' => 'Invoices'],
            'credit-notes' => ['class' => CreditNote::class, 'label' => 'Credit Notes'],
            'purchase-orders' => ['class' => PurchaseOrder::class, 'label' => 'Purchase Orders'],
            'reports' => ['class' => Report::class, 'label' => 'Reports'],
            'licenses' => ['class' => License::class, 'label' => 'Licenses'],
        ];
    }

    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $moduleMap = $this->moduleMap();

        return Inertia::render('notes/Index', [
            'modules' => collect($moduleMap)->map(function (array $config, string $key) {
                return [
                    'value' => $key,
                    'label' => $config['label'],
                ];
            })->values()->all(),
            'filters' => [
                'module' => $request->string('module')->toString(),
                'search' => $request->string('search')->toString(),
            ],
            'currentCompany' => $currentCompany,
        ]);
    }

    public function listForRecord(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string'],
            'record_id' => ['required', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $related = $this->resolveRelatedModel($data['module'], (int) $data['record_id']);
        if (! $related) {
            return response()->json(['message' => 'Related record not found.'], 404);
        }

        $perPage = (int) ($data['per_page'] ?? 10);
        $search = trim((string) ($data['search'] ?? ''));

        $query = Note::query()
            ->where('company_id', $related->company_id)
            ->where('noteable_type', $related->getMorphClass())
            ->where('noteable_id', $related->getKey())
            ->with('user:id,name')
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('attachment_original_name', 'like', "%{$search}%");
            });
        }

        $notes = $query->paginate($perPage)->withQueryString();

        return response()->json($notes);
    }

    public function listAll(Request $request): JsonResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $data = $request->validate([
            'module' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim((string) ($data['search'] ?? ''));
        $perPage = (int) ($data['per_page'] ?? 15);

        $query = Note::query()
            ->where('company_id', $currentCompany->id)
            ->with('user:id,name')
            ->orderByDesc('created_at');

        if (! empty($data['module'])) {
            $moduleConfig = $this->moduleMap()[$data['module']] ?? null;
            if ($moduleConfig) {
                $query->where('noteable_type', (new $moduleConfig['class'])->getMorphClass());
            }
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('attachment_original_name', 'like', "%{$search}%");
            });
        }

        $notes = $query->paginate($perPage)->withQueryString();
        $moduleByClass = collect($this->moduleMap())->mapWithKeys(
            fn (array $value, string $key): array => [(new $value['class'])->getMorphClass() => $key]
        );

        $notes->getCollection()->transform(function (Note $note) use ($moduleByClass) {
            $note->module = $moduleByClass[$note->noteable_type] ?? null;
            return $note;
        });

        return response()->json($notes);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string'],
            'record_id' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $related = $this->resolveRelatedModel($data['module'], (int) $data['record_id']);
        if (! $related) {
            return response()->json(['message' => 'Related record not found.'], 404);
        }

        $note = new Note([
            'company_id' => $related->company_id,
            'user_id' => auth()->id(),
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store("notes/{$related->company_id}", 'public');

            $note->attachment_path = $path;
            $note->attachment_original_name = $file->getClientOriginalName();
            $note->attachment_mime = $file->getClientMimeType();
            $note->attachment_size = $file->getSize();
        }

        $note->noteable()->associate($related);
        $note->save();
        $note->load('user:id,name');

        return response()->json([
            'message' => 'Note created successfully.',
            'note' => $note,
        ], 201);
    }

    public function relatedRecords(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $moduleConfig = $this->moduleMap()[$data['module']] ?? null;
        if (! $moduleConfig) {
            return response()->json([]);
        }

        $modelClass = $moduleConfig['class'];
        $companyId = auth()->user()->getCurrentCompany()->id;
        $search = trim((string) ($data['q'] ?? ''));

        $query = $modelClass::query()->where('company_id', $companyId);
        if ($search !== '') {
            $this->applyRecordSearch($query, $modelClass, $search);
        }

        $records = $query->latest('id')->limit(20)->get();

        return response()->json(
            $records->map(fn (Model $record) => [
                'id' => $record->getKey(),
                'label' => $this->recordLabel($record, $data['module']),
            ])->values()->all()
        );
    }

    public function download(Note $note)
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        abort_unless($note->company_id === $currentCompany->id, 403);
        abort_unless(! empty($note->attachment_path), 404);
        abort_unless(Storage::disk('public')->exists($note->attachment_path), 404);

        return response()->download(
            Storage::disk('public')->path($note->attachment_path),
            $note->attachment_original_name ?: basename($note->attachment_path)
        );
    }

    private function resolveRelatedModel(string $module, int $recordId): ?Model
    {
        $moduleConfig = $this->moduleMap()[$module] ?? null;
        if (! $moduleConfig) {
            return null;
        }

        $modelClass = $moduleConfig['class'];
        $companyId = auth()->user()->getCurrentCompany()->id;

        return $modelClass::query()
            ->where('company_id', $companyId)
            ->find($recordId);
    }

    private function applyRecordSearch(Builder $query, string $modelClass, string $search): void
    {
        $query->where(function (Builder $q) use ($modelClass, $search): void {
            $q->where('id', 'like', "%{$search}%");

            $candidateColumns = match ($modelClass) {
                Customer::class, Supplier::class, Contact::class, Product::class => ['name'],
                Jobcard::class => ['job_number', 'title'],
                Quote::class => ['quote_number', 'title'],
                Invoice::class => ['invoice_number', 'title'],
                CreditNote::class => ['credit_note_number', 'reference'],
                PurchaseOrder::class => ['po_number', 'reference'],
                Report::class => ['name'],
                ProductBatch::class => ['batch_number'],
                ProductSerialNumber::class => ['serial_number'],
                StockMovement::class => ['reference_number', 'movement_type', 'notes'],
                License::class => ['license_key', 'status'],
                default => [],
            };

            foreach ($candidateColumns as $column) {
                $q->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    private function recordLabel(Model $record, string $module): string
    {
        return match ($module) {
            'customers', 'suppliers', 'contacts', 'products' => (string) ($record->name ?? "#{$record->id}"),
            'jobcards' => (string) ($record->job_number ?? "#{$record->id}"),
            'quotes' => (string) ($record->quote_number ?? "#{$record->id}"),
            'invoices' => (string) ($record->invoice_number ?? "#{$record->id}"),
            'credit-notes' => (string) ($record->credit_note_number ?? "#{$record->id}"),
            'purchase-orders' => (string) ($record->po_number ?? "#{$record->id}"),
            'reports' => (string) ($record->name ?? "#{$record->id}"),
            'product-batches' => (string) ($record->batch_number ?? "#{$record->id}"),
            'product-serial-numbers' => (string) ($record->serial_number ?? "#{$record->id}"),
            'stock-movements' => 'Stock movement #'.$record->id,
            'licenses' => (string) ($record->license_key ?? "#{$record->id}"),
            default => "#{$record->id}",
        };
    }
}

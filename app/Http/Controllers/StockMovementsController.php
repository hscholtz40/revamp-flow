<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\CompanyScopedRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementsController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of stock movements.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortableFields = ['created_at', 'quantity', 'stock_before', 'stock_after', 'type', 'reference', 'product_name', 'user_name'];
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'created_at';
        }

        $movementsQuery = StockMovement::where('company_id', $currentCompany->id)
            ->with(['product', 'user'])
            ->when($request->filled('product_id'), function ($query) use ($request) {
                $query->where('product_id', $request->integer('product_id'));
            })
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->string('type'));
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->date('date_from'));
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->date('date_to'));
            });

        if ($sortBy === 'product_name') {
            $movementsQuery->orderBy(
                Product::select('name')->whereColumn('products.id', 'stock_movements.product_id')->limit(1),
                $sortDir
            );
        } elseif ($sortBy === 'user_name') {
            $movementsQuery->orderBy(
                \App\Models\User::select('name')->whereColumn('users.id', 'stock_movements.user_id')->limit(1),
                $sortDir
            );
        } else {
            $movementsQuery->orderBy($sortBy, $sortDir);
        }

        $movements = $movementsQuery->paginate(20)->withQueryString();

        return Inertia::render('stock-movements/Index', [
            'movements' => $movements,
            'filters' => [
                'product_id' => $request->integer('product_id'),
                'type' => $request->string('type')->toString(),
                'date_from' => $request->date('date_from')?->format('Y-m-d'),
                'date_to' => $request->date('date_to')?->format('Y-m-d'),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'products' => Product::where('company_id', $currentCompany->id)
                ->where('track_stock', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku']),
        ]);
    }

    /**
     * Show the form for creating a new stock movement.
     */
    public function create(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $user = auth()->user();

        // Get companies the user has access to (excluding current company for transfers)
        if ($user->companies()->count() === 0) {
            $companies = \App\Models\Company::where('is_active', true)
                ->where('id', '!=', $currentCompany->id)
                ->orderBy('name')
                ->get(['id', 'name']);
        } else {
            $companies = $user->companies()
                ->where('is_active', true)
                ->where('companies.id', '!=', $currentCompany->id)
                ->orderBy('companies.name')
                ->get(['companies.id', 'companies.name']);
        }

        $products = Product::where('company_id', $currentCompany->id)
            ->where('track_stock', true)
            ->with(['serialNumbers' => function ($query) {
                $query->where('status', 'available');
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock_quantity' => $product->stock_quantity,
                    'track_serial_numbers' => $product->track_serial_numbers,
                    'serialNumbers' => $product->serialNumbers->map(function ($serial) {
                        return [
                            'id' => $serial->id,
                            'serial_number' => $serial->serial_number,
                            'status' => $serial->status,
                        ];
                    })->toArray(),
                ];
            });

        return Inertia::render('stock-movements/Create', [
            'product_id' => $request->integer('product_id'),
            'products' => $products,
            'companies' => $companies,
            'currentCompany' => $currentCompany,
        ]);
    }

    /**
     * Store a newly created stock movement.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $user = auth()->user();

        $cid = $currentCompany->id;

        $validator = Validator::make($request->all(), [
            'product_id' => ['required', CompanyScopedRules::product($cid)],
            'type' => ['required', 'string', 'in:in,out,adjustment,transfer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'to_company_id' => ['required_if:type,transfer', 'nullable', 'exists:companies,id'],
            'serial_number_ids' => ['nullable', 'array'],
            'serial_number_ids.*' => [CompanyScopedRules::productSerialNumberForCompany($cid)],
            'reference' => ['nullable', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);
        $validator->after(CompanyScopedRules::afterTransferDestinationCompanyAccessible());
        $validator->after(CompanyScopedRules::afterValidateStockMovementSerials($cid));
        $validated = $validator->validate();

        $product = Product::findOrFail($validated['product_id']);

        try {
            $quantity = $validated['quantity'];

            if ($validated['type'] === 'adjustment') {
                $this->stockService->adjustStock(
                    $product,
                    $quantity,
                    $validated['notes'] ?? null
                );
            } elseif ($validated['type'] === 'transfer') {
                // Validate user has access to destination company
                $toCompanyId = $validated['to_company_id'];
                if (! $user->hasAccessToCompany($toCompanyId)) {
                    abort(403, 'You do not have access to the destination company.');
                }

                $this->stockService->transferStock(
                    $product,
                    $toCompanyId,
                    $quantity,
                    $validated['serial_number_ids'] ?? null,
                    $validated['notes'] ?? null
                );
            } elseif ($validated['type'] === 'in') {
                $this->stockService->addStock(
                    $product,
                    $quantity,
                    $validated['unit_cost'] ?? null,
                    $validated['reference'] ?? null,
                    $validated['reference_type'] ?? null,
                    $validated['reference_id'] ?? null,
                    $validated['notes'] ?? null,
                    null,
                    $validated['serial_number_ids'] ?? null
                );
            } else { // 'out'
                $this->stockService->removeStock(
                    $product,
                    $quantity,
                    $validated['reference'] ?? null,
                    $validated['reference_type'] ?? null,
                    $validated['reference_id'] ?? null,
                    $validated['notes'] ?? null,
                    $validated['serial_number_ids'] ?? null
                );
            }

            return redirect()->route('stock-movements.index')
                ->with('success', 'Stock movement recorded successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified stock movement.
     */
    public function show(StockMovement $stockMovement): Response
    {
        $this->authorize('view', $stockMovement);

        $currentCompany = auth()->user()->getCurrentCompany();

        $stockMovement->load(['product', 'user', 'toCompany', 'serialNumber']);

        // Load reference if it exists (polymorphic relationship)
        // Skip loading reference for 'transfer' type as it's not a model class
        if ($stockMovement->reference_type && $stockMovement->reference_id && $stockMovement->reference_type !== 'transfer') {
            try {
                $stockMovement->load('reference');
            } catch (\Exception $e) {
                // Reference might not exist or morph map not configured
                Log::warning('Failed to load reference for stock movement', [
                    'stock_movement_id' => $stockMovement->id,
                    'reference_type' => $stockMovement->reference_type,
                    'reference_id' => $stockMovement->reference_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Get serial numbers for this movement
        // When tracking serial numbers, each movement record has one serial number
        // For transfers with multiple serials, multiple movement records are created
        $serialNumbers = [];

        // Get the serial number for this movement if it exists
        if ($stockMovement->product_serial_number_id && $stockMovement->serialNumber) {
            $serialNumbers[] = $stockMovement->serialNumber;
        }

        // For transfers, try to find related movements with serial numbers
        // Look for movements with the same to_company_id and created around the same time
        if ($stockMovement->type === 'transfer' && $stockMovement->to_company_id) {
            $createdAt = \Carbon\Carbon::parse($stockMovement->created_at);
            $relatedMovements = \App\Models\StockMovement::where(function ($query) use ($stockMovement, $createdAt) {
                // Find other movements in the same transfer operation
                // They should have the same to_company_id and be created within a few seconds
                $query->where('to_company_id', $stockMovement->to_company_id)
                    ->where('product_id', $stockMovement->product_id)
                    ->whereBetween('created_at', [
                        $createdAt->copy()->subSeconds(5),
                        $createdAt->copy()->addSeconds(5),
                    ])
                    ->where('id', '!=', $stockMovement->id);
            })
                ->whereNotNull('product_serial_number_id')
                ->with('serialNumber')
                ->get();

            // Add serial numbers from related movements
            foreach ($relatedMovements as $relatedMovement) {
                if ($relatedMovement->serialNumber && ! collect($serialNumbers)->contains('id', $relatedMovement->serialNumber->id)) {
                    $serialNumbers[] = $relatedMovement->serialNumber;
                }
            }
        }

        // Convert to array and add serial numbers
        $movementData = $stockMovement->toArray();
        $movementData['serialNumbers'] = collect($serialNumbers)->map(function ($serial) {
            return $serial ? [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'status' => $serial->status,
            ] : null;
        })->filter()->values()->all();

        return Inertia::render('stock-movements/Show', [
            'movement' => $movementData,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\SystemNotificationService;
use App\Support\CompanyScopedRules;
use App\Support\CsvExport;
use App\Support\ProductImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $sortableFields = ['name', 'type', 'sku', 'category', 'price', 'stock_quantity', 'is_active', 'created_at'];
        if (!in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'name';
        }
        $query = Product::where('company_id', $currentCompany->id);

        // Apply filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active_only')) {
            $query->where('is_active', true);
        }

        $products = $query->orderBy($sortBy, $sortDir)->paginate(15)->withQueryString();

        // Get active categories for filter dropdown
        $categories = Category::active()->ordered()->pluck('name');

        // Calculate totals for all products (not just paginated)
        // Base query with filters (excluding active_only for totals calculation)
        $baseTotalsQuery = Product::where('company_id', $currentCompany->id);
        
        // Apply filters for totals (but not active_only - we'll calculate active/inactive separately)
        if ($request->filled('type')) {
            $baseTotalsQuery->where('type', $request->type);
        }
        if ($request->filled('category')) {
            $baseTotalsQuery->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $baseTotalsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Calculate totals using cloned queries to avoid query builder mutation
        $totals = [
            'total_products' => (clone $baseTotalsQuery)->count(),
            'total_products_type' => (clone $baseTotalsQuery)->where('type', 'product')->count(),
            'total_services_type' => (clone $baseTotalsQuery)->where('type', 'service')->count(),
            'total_active' => (clone $baseTotalsQuery)->where('is_active', true)->count(),
            'total_inactive' => (clone $baseTotalsQuery)->where('is_active', false)->count(),
            'total_stock_value' => (clone $baseTotalsQuery)
                ->where('type', 'product')
                ->where('track_stock', true)
                ->get()
                ->sum(function ($product) {
                    return ($product->stock_quantity ?? 0) * ($product->cost_price ?? $product->cost ?? 0);
                }),
            'total_selling_value' => (clone $baseTotalsQuery)->get()->sum(function ($product) {
                return ($product->stock_quantity ?? 0) * ($product->selling_price ?? $product->price ?? 0);
            }),
        ];

        return Inertia::render('products/Index', [
            'products' => $products,
            'filters' => [
                'type' => $request->input('type', ''),
                'category' => $request->input('category', ''),
                'search' => $request->input('search', ''),
                'active_only' => $request->boolean('active_only', false),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'categories' => $categories,
            'currentCompany' => $currentCompany,
            'totals' => $totals,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $sortableFields = ['name', 'type', 'sku', 'category', 'price', 'stock_quantity', 'is_active', 'created_at'];
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'name';
        }

        $query = Product::where('company_id', $currentCompany->id);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active_only') && $request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $rows = $query->orderBy($sortBy, $sortDir)->get()->map(function (Product $product) {
            $tags = is_array($product->tags) ? implode('; ', $product->tags) : (string) ($product->tags ?? '');

            return [
                $product->id,
                $product->name,
                $product->type,
                $product->sku,
                $product->barcode,
                $product->category,
                $product->description,
                $product->unit,
                $product->weight,
                $product->length,
                $product->width,
                $product->height,
                $product->color,
                $product->size,
                $product->price,
                $product->cost,
                $product->track_stock,
                $product->stock_quantity,
                $product->min_stock_level,
                $product->is_active,
                $tags,
                $product->notes,
                optional($product->created_at)?->format('Y-m-d H:i:s'),
            ];
        });

        return CsvExport::download('products_'.date('Y-m-d_His').'.csv', [
            'ID',
            'Name',
            'Type',
            'SKU',
            'Barcode',
            'Category',
            'Description',
            'Unit',
            'Weight (kg)',
            'Length (cm)',
            'Width (cm)',
            'Height (cm)',
            'Color',
            'Size',
            'Price',
            'Cost',
            'Track Stock',
            'Stock Quantity',
            'Min Stock Level',
            'Active',
            'Tags',
            'Notes',
            'Created At',
        ], $rows);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $categories = Category::active()->ordered()->get(['id', 'name', 'color']);
        $chartOfAccounts = \App\Models\ChartOfAccount::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->ordered()
            ->get(['id', 'account_code', 'account_name', 'account_type']);
        $suppliers = Supplier::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('products/Create', [
            'categories' => $categories,
            'currentCompany' => $currentCompany,
            'chartOfAccounts' => $chartOfAccounts,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:product,service'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->where(fn ($query) => $query->where('company_id', $currentCompany->id)),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'barcode')->where(fn ($query) => $query->where('company_id', $currentCompany->id)),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            ...$this->physicalAttributeRules(),
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['boolean'],
            'track_batches' => ['boolean'],
            'track_serial_numbers' => ['boolean'],
            'valuation_method' => ['nullable', 'in:fifo,lifo,average_cost'],
            'is_active' => ['boolean'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'is_licensing_package' => ['sometimes', 'boolean'],
            'package_code' => ['nullable', 'string', 'max:50'],
            'license_standard_users' => ['nullable', 'integer', 'min:0'],
            'license_limited_users' => ['nullable', 'integer', 'min:0'],
            'monthly_credits' => ['nullable', 'integer', 'min:0'],
            'supplier_id' => ['nullable', CompanyScopedRules::supplier($currentCompany->id)],
        ]);

        // For services, don't track stock
        if ($validated['type'] === 'service') {
            $validated['track_stock'] = false;
            $validated['stock_quantity'] = 0;
        }

        $validated['company_id'] = $currentCompany->id;
        $validated['is_licensing_package'] = $request->boolean('is_licensing_package');
        $validated['image_path'] = ProductImageStorage::resolvePath($request, $currentCompany->id);
        unset($validated['image'], $validated['remove_image']);
        $product = Product::create($validated);

        if (($request->expectsJson() || $request->ajax()) && ! $request->header('X-Inertia')) {
            return response()->json([
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type,
                'price' => $product->price,
                'cost' => $product->cost,
                'sku' => $product->sku,
                'supplier_id' => $product->supplier_id,
                'category' => $product->category,
            ], 201);
        }

        return redirect()->route('products.index')
            ->with('success', 'Product created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Product $product): Response
    {
        $product->load('supplier', 'batches', 'serialNumbers');

        $sortBy = $request->input('invoice_usage_sort', 'date');
        $sortDir = $request->input('invoice_usage_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortable = ['date', 'invoice_number', 'customer', 'unit_price'];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'date';
        }

        $user = $request->user();
        if ($user->hasModulePermission('invoices', 'view')) {
            $invoiceLineItemsQuery = InvoiceLineItem::where('invoice_line_items.product_id', $product->id)
                ->whereHas('invoice', fn ($q) => $q->where('company_id', $product->company_id))
                ->with(['invoice:id,invoice_number,customer_id,invoice_date', 'invoice.customer:id,name']);

            match ($sortBy) {
                'date' => $invoiceLineItemsQuery->orderBy(
                    Invoice::select('invoice_date')->whereColumn('invoices.id', 'invoice_line_items.invoice_id')->limit(1),
                    $sortDir
                )->orderBy('invoice_line_items.id', $sortDir),
                'invoice_number' => $invoiceLineItemsQuery->orderBy(
                    Invoice::select('invoice_number')->whereColumn('invoices.id', 'invoice_line_items.invoice_id')->limit(1),
                    $sortDir
                )->orderBy('invoice_line_items.id', $sortDir),
                'customer' => $invoiceLineItemsQuery
                    ->join('invoices', 'invoice_line_items.invoice_id', '=', 'invoices.id')
                    ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
                    ->orderBy('customers.name', $sortDir)
                    ->orderBy('invoice_line_items.id', $sortDir),
                'unit_price' => $invoiceLineItemsQuery->orderBy('invoice_line_items.unit_price', $sortDir)->orderBy('invoice_line_items.id', $sortDir),
                default => $invoiceLineItemsQuery->orderBy('invoice_line_items.id', 'desc'),
            };

            $recentInvoiceLineItems = $invoiceLineItemsQuery
                ->paginate(10, ['invoice_line_items.id', 'invoice_line_items.invoice_id', 'invoice_line_items.product_id', 'invoice_line_items.description', 'invoice_line_items.quantity', 'invoice_line_items.unit_price', 'invoice_line_items.total'], 'invoice_usage_page')
                ->withQueryString()
                ->appends([
                    'invoice_usage_sort' => $sortBy,
                    'invoice_usage_dir' => $sortDir,
                ]);
        } else {
            $recentInvoiceLineItems = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, 1, [
                'path' => $request->url(),
                'pageName' => 'invoice_usage_page',
            ]);
        }

        return Inertia::render('products/Show', [
            'product' => $product,
            'recentInvoiceLineItems' => $recentInvoiceLineItems,
            'invoiceUsageSort' => $sortBy,
            'invoiceUsageDir' => $sortDir,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $categories = Category::active()->ordered()->get(['id', 'name', 'color']);
        $chartOfAccounts = \App\Models\ChartOfAccount::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->ordered()
            ->get(['id', 'account_code', 'account_name', 'account_type']);
        $suppliers = Supplier::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('products/Edit', [
            'product' => $product->load('supplier'),
            'categories' => $categories,
            'currentCompany' => $currentCompany,
            'chartOfAccounts' => $chartOfAccounts,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:product,service'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('company_id', $currentCompany->id))
                    ->ignore($product->id),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'barcode')
                    ->where(fn ($query) => $query->where('company_id', $currentCompany->id))
                    ->ignore($product->id),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            ...$this->physicalAttributeRules(),
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['boolean'],
            'track_batches' => ['boolean'],
            'track_serial_numbers' => ['boolean'],
            'valuation_method' => ['nullable', 'in:fifo,lifo,average_cost'],
            'is_active' => ['boolean'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'purchase_account_code' => ['nullable', 'string', 'max:50'],
            'sales_account_code' => ['nullable', 'string', 'max:50'],
            'is_licensing_package' => ['sometimes', 'boolean'],
            'package_code' => ['nullable', 'string', 'max:50'],
            'license_standard_users' => ['nullable', 'integer', 'min:0'],
            'license_limited_users' => ['nullable', 'integer', 'min:0'],
            'monthly_credits' => ['nullable', 'integer', 'min:0'],
            'supplier_id' => ['nullable', CompanyScopedRules::supplier($currentCompany->id)],
        ]);

        // For services, don't track stock
        if ($validated['type'] === 'service') {
            $validated['track_stock'] = false;
            $validated['stock_quantity'] = 0;
        }

        $before = $product->only(array_keys($validated));

        $validated['is_licensing_package'] = $request->boolean('is_licensing_package');
        $validated['image_path'] = ProductImageStorage::resolvePath(
            $request,
            $currentCompany->id,
            $product->image_path,
        );
        unset($validated['image'], $validated['remove_image']);
        $product->update($validated);

        app(SystemNotificationService::class)->notifyCatalogItemUpdated(
            $currentCompany,
            $product->name,
            (string) $product->type,
            $before,
            $product->only(array_keys($validated))
        );

        return redirect()->route('products.show', $product)
            ->with('success', 'Product updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        // Check if product is referenced in purchase orders (which have restrict constraint)
        $purchaseOrderItemsCount = PurchaseOrderItem::where('product_id', $product->id)->count();
        
        if ($purchaseOrderItemsCount > 0) {
            return redirect()->route('products.index')
                ->with('error', "Cannot delete product '{$product->name}' because it is referenced in {$purchaseOrderItemsCount} purchase order item(s). Please remove it from all purchase orders first.");
        }

        try {
            ProductImageStorage::delete($product->image_path);
            $product->delete();

            return redirect()->route('products.index')
                ->with('success', 'Product deleted successfully');
        } catch (\Illuminate\Database\QueryException $e) {
            // Catch any other foreign key constraint violations
            if ($e->getCode() === '23000') {
                return redirect()->route('products.index')
                    ->with('error', "Cannot delete product '{$product->name}' because it is still being used in the system. Please remove all references to this product first.");
            }
            
            throw $e;
        }
    }

    /**
     * Search product by barcode.
     */
    public function searchByBarcode(Request $request)
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $barcode = $request->input('barcode');

        if (!$barcode) {
            return response()->json(['error' => 'Barcode is required'], 400);
        }

        $product = Product::where('company_id', $currentCompany->id)
            ->where('barcode', $barcode)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json([
            'product' => $product->load('supplier', 'batches', 'serialNumbers'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function physicalAttributeRules(): array
    {
        return [
            'weight' => ['nullable', 'numeric', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:100'],
        ];
    }
}

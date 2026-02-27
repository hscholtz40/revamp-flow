<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        return Inertia::render('products/Create', [
            'categories' => $categories,
            'currentCompany' => $currentCompany,
            'chartOfAccounts' => $chartOfAccounts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:product,service'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:255', 'unique:products,barcode'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
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
            'image_path' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        // For services, don't track stock
        if ($validated['type'] === 'service') {
            $validated['track_stock'] = false;
            $validated['stock_quantity'] = 0;
        }

        $validated['company_id'] = $currentCompany->id;
        Product::create($validated);

        return redirect()->route('products.index')
            ->with('success', 'Product created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): Response
    {
        $product->load('supplier', 'batches', 'serialNumbers');
        
        return Inertia::render('products/Show', [
            'product' => $product,
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

        return Inertia::render('products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'currentCompany' => $currentCompany,
            'chartOfAccounts' => $chartOfAccounts,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:product,service'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku,' . $product->id],
            'barcode' => ['nullable', 'string', 'max:255', 'unique:products,barcode,' . $product->id],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
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
            'image_path' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'purchase_account_code' => ['nullable', 'string', 'max:50'],
            'sales_account_code' => ['nullable', 'string', 'max:50'],
        ]);

        // For services, don't track stock
        if ($validated['type'] === 'service') {
            $validated['track_stock'] = false;
            $validated['stock_quantity'] = 0;
        }

        $product->update($validated);

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
}

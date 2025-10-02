<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
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
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active_only')) {
            $query->where('is_active', true);
        }

        $products = $query->orderBy('name')->paginate(15);

        // Get active categories for filter dropdown
        $categories = Category::active()->ordered()->pluck('name');

        return Inertia::render('products/Index', [
            'products' => $products,
            'filters' => [
                'type' => $request->input('type', ''),
                'category' => $request->input('category', ''),
                'search' => $request->input('search', ''),
                'active_only' => $request->boolean('active_only', false),
            ],
            'categories' => $categories,
            'currentCompany' => $currentCompany,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $categories = Category::active()->ordered()->get(['id', 'name', 'color']);

        return Inertia::render('products/Create', [
            'categories' => $categories,
            'currentCompany' => $currentCompany,
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
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['boolean'],
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

        return Inertia::render('products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'currentCompany' => $currentCompany,
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
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['boolean'],
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

        $product->update($validated);

        return redirect()->route('products.show', $product)
            ->with('success', 'Product updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully');
    }
}

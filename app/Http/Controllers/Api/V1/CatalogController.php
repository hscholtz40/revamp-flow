<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function products(Request $request)
    {
        return response()->json(
            Product::query()->where('company_id', $this->companyId($request))->orderBy('name')->paginate(25)
        );
    }

    public function showProduct(Request $request, Product $product)
    {
        $this->assertCompanyRecord($request, (int) $product->company_id);

        return response()->json($product);
    }

    public function storeProduct(Request $request)
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:product,service'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric'],
            'cost' => ['nullable', 'numeric'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:100'],
            'track_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product = Product::create([
            ...$payload,
            'company_id' => $this->companyId($request),
            'type' => $payload['type'] ?? 'product',
            'is_active' => $payload['is_active'] ?? true,
        ]);

        return response()->json($product, 201);
    }

    public function updateProduct(Request $request, Product $product)
    {
        $this->assertCompanyRecord($request, (int) $product->company_id);
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:product,service'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric'],
            'cost' => ['nullable', 'numeric'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:100'],
            'stock_quantity' => ['nullable', 'integer'],
            'track_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $product->update($payload);

        return response()->json($product->fresh());
    }

    public function destroyProduct(Request $request, Product $product)
    {
        $this->assertCompanyRecord($request, (int) $product->company_id);
        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }

    public function suppliers(Request $request)
    {
        return response()->json(
            Supplier::query()->where('company_id', $this->companyId($request))->orderBy('name')->paginate(25)
        );
    }

    public function showSupplier(Request $request, Supplier $supplier)
    {
        $this->assertCompanyRecord($request, (int) $supplier->company_id);

        return response()->json($supplier);
    }

    public function storeSupplier(Request $request)
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $supplier = Supplier::create([
            ...$payload,
            'company_id' => $this->companyId($request),
            'is_active' => $payload['is_active'] ?? true,
        ]);

        return response()->json($supplier, 201);
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $this->assertCompanyRecord($request, (int) $supplier->company_id);
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $supplier->update($payload);

        return response()->json($supplier->fresh());
    }

    public function destroySupplier(Request $request, Supplier $supplier)
    {
        $this->assertCompanyRecord($request, (int) $supplier->company_id);
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted']);
    }

    public function searchSuppliers(Request $request)
    {
        $term = (string) $request->query('q', '');

        return response()->json(
            Supplier::query()
                ->where('company_id', $this->companyId($request))
                ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                ->limit(20)
                ->get(['id', 'name', 'email', 'phone'])
        );
    }

    public function stockMovements(Request $request)
    {
        return response()->json(
            StockMovement::query()
                ->where('company_id', $this->companyId($request))
                ->with(['product:id,name', 'user:id,name'])
                ->orderByDesc('id')
                ->paginate(25)
        );
    }

    public function showStockMovement(Request $request, StockMovement $movement)
    {
        $this->assertCompanyRecord($request, (int) $movement->company_id);

        return response()->json($movement->load(['product:id,name', 'user:id,name']));
    }

    public function storeStockMovement(Request $request)
    {
        $payload = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'type' => ['required', 'in:in,out,adjustment,transfer'],
            'quantity' => ['required', 'integer'],
            'unit_cost' => ['nullable', 'numeric'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $movement = StockMovement::create([
            ...$payload,
            'company_id' => $this->companyId($request),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($movement, 201);
    }

    private function companyId(Request $request): int
    {
        return (int) ($request->user()->getCurrentCompany()?->id ?? 0);
    }

    private function assertCompanyRecord(Request $request, int $recordCompanyId): void
    {
        abort_unless($recordCompanyId === $this->companyId($request), 404);
    }
}

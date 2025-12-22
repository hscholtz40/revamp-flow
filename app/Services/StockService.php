<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductSerialNumber;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Record a stock movement and update product stock level.
     *
     * @param Product $product
     * @param string $type 'in', 'out', 'adjustment', 'transfer'
     * @param int $quantity Positive for 'in', negative for 'out'
     * @param float|null $unitCost Cost per unit
     * @param string|null $reference Reference text
     * @param string|null $referenceType Type of reference (e.g., 'purchase_order', 'invoice')
     * @param int|null $referenceId ID of the reference
     * @param string|null $notes Additional notes
     * @param int|null $batchId Product batch ID
     * @param array|null $serialNumberIds Array of serial number IDs
     * @return StockMovement
     */
    public function recordMovement(
        Product $product,
        string $type,
        int $quantity,
        ?float $unitCost = null,
        ?string $reference = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $batchId = null,
        ?array $serialNumberIds = null
    ): StockMovement {
        if (!$product->track_stock) {
            throw new \Exception('Stock tracking is disabled for this product');
        }

        return DB::transaction(function () use (
            $product,
            $type,
            $quantity,
            $unitCost,
            $reference,
            $referenceType,
            $referenceId,
            $notes,
            $batchId,
            $serialNumberIds
        ) {
            $stockBefore = $product->stock_quantity;
            
            // Calculate new stock level
            $stockAfter = match ($type) {
                'in' => $stockBefore + abs($quantity),
                'out' => max(0, $stockBefore - abs($quantity)), // Prevent negative stock
                'adjustment' => $quantity, // Direct set
                'transfer' => $stockBefore - abs($quantity),
                default => $stockBefore,
            };

            // Handle serial numbers for outgoing stock
            if ($type === 'out' && $serialNumberIds && count($serialNumberIds) > 0) {
                // Update serial numbers to sold status
                foreach ($serialNumberIds as $serialId) {
                    $serial = ProductSerialNumber::find($serialId);
                    if ($serial && $serial->product_id === $product->id && $serial->status === 'available') {
                        $serial->update([
                            'status' => 'sold',
                            'sale_date' => now(),
                            'invoice_id' => $referenceType === 'invoice' ? $referenceId : null,
                        ]);
                    }
                }
            }

            // Handle serial numbers for incoming stock
            if ($type === 'in' && $serialNumberIds && count($serialNumberIds) > 0) {
                // Update serial numbers to available status
                foreach ($serialNumberIds as $serialId) {
                    $serial = ProductSerialNumber::find($serialId);
                    if ($serial && $serial->product_id === $product->id) {
                        $serial->update([
                            'status' => 'available',
                            'purchase_date' => now(),
                        ]);
                    }
                }
            }

            // Create stock movement record (one per serial number if tracking serials, otherwise one per movement)
            $movements = [];
            if ($serialNumberIds && count($serialNumberIds) > 0 && $product->track_serial_numbers) {
                // Create one movement per serial number
                foreach ($serialNumberIds as $serialId) {
                    $serial = ProductSerialNumber::find($serialId);
                    if ($serial) {
                        $movements[] = StockMovement::create([
                            'company_id' => $product->company_id,
                            'product_id' => $product->id,
                            'type' => $type,
                            'quantity' => 1,
                            'unit_cost' => $unitCost,
                            'reference' => $reference,
                            'reference_type' => $referenceType,
                            'reference_id' => $referenceId,
                            'notes' => $notes,
                            'user_id' => auth()->id(),
                            'stock_before' => $stockBefore,
                            'stock_after' => $stockAfter,
                            'product_batch_id' => $batchId,
                            'product_serial_number_id' => $serialId,
                        ]);
                        $stockBefore = $stockAfter; // Update for next iteration
                    }
                }
                $movement = $movements[0] ?? null;
            } else {
                // Create single movement
                $movement = StockMovement::create([
                    'company_id' => $product->company_id,
                    'product_id' => $product->id,
                    'type' => $type,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'reference' => $reference,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'notes' => $notes,
                    'user_id' => auth()->id(),
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'product_batch_id' => $batchId,
                    'product_serial_number_id' => null,
                ]);
            }

            // Update product stock level
            $product->update(['stock_quantity' => $stockAfter]);

            Log::info('Stock movement recorded', [
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
            ]);

            return $movement;
        });
    }

    /**
     * Add stock (incoming).
     */
    public function addStock(
        Product $product,
        int $quantity,
        ?float $unitCost = null,
        ?string $reference = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $batchId = null,
        ?array $serialNumberIds = null
    ): StockMovement {
        return $this->recordMovement(
            $product,
            'in',
            abs($quantity),
            $unitCost,
            $reference,
            $referenceType,
            $referenceId,
            $notes,
            $batchId,
            $serialNumberIds
        );
    }

    /**
     * Remove stock (outgoing).
     */
    public function removeStock(
        Product $product,
        int $quantity,
        ?string $reference = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?array $serialNumberIds = null
    ): StockMovement {
        if ($product->stock_quantity < $quantity) {
            throw new \Exception("Insufficient stock. Available: {$product->stock_quantity}, Requested: {$quantity}");
        }

        // If tracking serial numbers, validate that we have enough available serials
        if ($product->track_serial_numbers && $serialNumberIds) {
            $serialCount = count($serialNumberIds);
            if ($serialCount !== $quantity) {
                throw new \Exception("Number of serial numbers ({$serialCount}) must match quantity ({$quantity})");
            }
            
            // Validate all serials are available
            $availableSerials = ProductSerialNumber::whereIn('id', $serialNumberIds)
                ->where('product_id', $product->id)
                ->where('status', 'available')
                ->count();
            
            if ($availableSerials !== count($serialNumberIds)) {
                throw new \Exception("One or more serial numbers are not available");
            }
        }

        return $this->recordMovement(
            $product,
            'out',
            -abs($quantity),
            null,
            $reference,
            $referenceType,
            $referenceId,
            $notes,
            null,
            $serialNumberIds
        );
    }

    /**
     * Adjust stock to a specific level.
     */
    public function adjustStock(
        Product $product,
        int $newQuantity,
        ?string $notes = null
    ): StockMovement {
        return $this->recordMovement(
            $product,
            'adjustment',
            $newQuantity,
            null,
            'Stock Adjustment',
            'adjustment',
            null,
            $notes
        );
    }

    /**
     * Transfer stock between companies.
     *
     * @param Product $sourceProduct Product in source company
     * @param int $toCompanyId Destination company ID
     * @param int $quantity Quantity to transfer
     * @param array|null $serialNumberIds Array of serial number IDs to transfer
     * @param string|null $notes Additional notes
     * @return array Array with 'outgoing' and 'incoming' StockMovement records
     */
    public function transferStock(
        Product $sourceProduct,
        int $toCompanyId,
        int $quantity,
        ?array $serialNumberIds = null,
        ?string $notes = null
    ): array {
        if (!$sourceProduct->track_stock) {
            throw new \Exception('Stock tracking is disabled for this product');
        }

        if ($sourceProduct->company_id === $toCompanyId) {
            throw new \Exception('Cannot transfer stock to the same company');
        }

        if ($sourceProduct->stock_quantity < $quantity) {
            throw new \Exception("Insufficient stock. Available: {$sourceProduct->stock_quantity}, Requested: {$quantity}");
        }

        // If tracking serial numbers, validate that we have enough available serials
        if ($sourceProduct->track_serial_numbers && $serialNumberIds) {
            $serialCount = count($serialNumberIds);
            if ($serialCount !== $quantity) {
                throw new \Exception("Number of serial numbers ({$serialCount}) must match quantity ({$quantity})");
            }
            
            // Validate all serials are available and belong to source product
            $availableSerials = ProductSerialNumber::whereIn('id', $serialNumberIds)
                ->where('product_id', $sourceProduct->id)
                ->where('company_id', $sourceProduct->company_id)
                ->where('status', 'available')
                ->count();
            
            if ($availableSerials !== count($serialNumberIds)) {
                throw new \Exception("One or more serial numbers are not available or do not belong to this product");
            }
        }

        return DB::transaction(function () use (
            $sourceProduct,
            $toCompanyId,
            $quantity,
            $serialNumberIds,
            $notes
        ) {
            // Find or create the product in the destination company
            // First, try to find by SKU in the destination company
            $destinationProduct = null;
            if ($sourceProduct->sku) {
                $destinationProduct = Product::where('company_id', $toCompanyId)
                    ->where('sku', $sourceProduct->sku)
                    ->first();
            }
            
            // If not found by SKU, try to find by name in the destination company
            if (!$destinationProduct) {
                $destinationProduct = Product::where('company_id', $toCompanyId)
                    ->where('name', $sourceProduct->name)
                    ->first();
            }

            // If product doesn't exist in destination company, create it
            // SKU is now unique per company, so we can safely use the same SKU
            if (!$destinationProduct) {
                $destinationProduct = Product::create([
                    'company_id' => $toCompanyId,
                    'name' => $sourceProduct->name,
                    'description' => $sourceProduct->description,
                    'type' => $sourceProduct->type,
                    'sku' => $sourceProduct->sku, // SKU is unique per company, so this is safe
                    'price' => $sourceProduct->price,
                    'cost' => $sourceProduct->cost,
                    'unit' => $sourceProduct->unit,
                    'stock_quantity' => 0,
                    'min_stock_level' => $sourceProduct->min_stock_level,
                    'track_stock' => $sourceProduct->track_stock,
                    'track_batches' => $sourceProduct->track_batches,
                    'track_serial_numbers' => $sourceProduct->track_serial_numbers,
                    'is_active' => $sourceProduct->is_active,
                    'category' => $sourceProduct->category,
                    'tags' => $sourceProduct->tags,
                    'valuation_method' => $sourceProduct->valuation_method,
                ]);
            }

            // Record outgoing movement from source company
            // Don't use polymorphic relationship for transfers - set reference_type to null
            $outgoingMovement = $this->recordMovement(
                $sourceProduct,
                'transfer',
                -abs($quantity),
                null,
                "Transfer to Company #{$toCompanyId}",
                null, // Don't use polymorphic relationship for transfers
                null, // Don't use polymorphic relationship for transfers
                $notes,
                null,
                $serialNumberIds
            );

            // Update the outgoing movement to link to destination company
            $outgoingMovement->update([
                'to_company_id' => $toCompanyId,
            ]);

            // Update serial numbers to destination company if tracking serials
            if ($sourceProduct->track_serial_numbers && $serialNumberIds) {
                foreach ($serialNumberIds as $serialId) {
                    $serial = ProductSerialNumber::find($serialId);
                    if ($serial) {
                        // Update serial number to destination company and product
                        $serial->update([
                            'company_id' => $toCompanyId,
                            'product_id' => $destinationProduct->id,
                        ]);
                    }
                }
            }

            // Record incoming movement to destination company
            // Note: serial numbers are already updated to destination company, so we can pass them
            // Don't use polymorphic relationship for transfers - set reference_type to null
            $incomingMovement = $this->recordMovement(
                $destinationProduct,
                'in',
                abs($quantity),
                $sourceProduct->cost ?? null,
                "Transfer from Company #{$sourceProduct->company_id}",
                null, // Don't use polymorphic relationship for transfers
                null, // Don't use polymorphic relationship for transfers
                $notes,
                null,
                $serialNumberIds
            );

            // Update the incoming movement to link to source company
            $incomingMovement->update([
                'to_company_id' => $sourceProduct->company_id,
            ]);

            Log::info('Stock transferred between companies', [
                'source_product_id' => $sourceProduct->id,
                'source_company_id' => $sourceProduct->company_id,
                'destination_product_id' => $destinationProduct->id,
                'destination_company_id' => $toCompanyId,
                'quantity' => $quantity,
                'outgoing_movement_id' => $outgoingMovement->id,
                'incoming_movement_id' => $incomingMovement->id,
            ]);

            return [
                'outgoing' => $outgoingMovement,
                'incoming' => $incomingMovement,
            ];
        });
    }

    /**
     * Get products with low stock.
     */
    public function getLowStockProducts(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('company_id', $companyId)
            ->where('track_stock', true)
            ->where('is_active', true)
            ->get()
            ->filter(function ($product) {
                return $product->isLowStock();
            });
    }
}


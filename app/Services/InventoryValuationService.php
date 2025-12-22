<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryValuationService
{
    /**
     * Calculate the cost of goods sold (COGS) based on valuation method.
     *
     * @param Product $product
     * @param int $quantity
     * @return float
     */
    public function calculateCOGS(Product $product, int $quantity): float
    {
        $method = $product->valuation_method ?? 'fifo';
        
        return match($method) {
            'fifo' => $this->calculateFIFO($product, $quantity),
            'lifo' => $this->calculateLIFO($product, $quantity),
            'average_cost' => $this->calculateAverageCost($product, $quantity),
            default => $this->calculateFIFO($product, $quantity),
        };
    }

    /**
     * Calculate FIFO (First In, First Out) cost.
     */
    protected function calculateFIFO(Product $product, int $quantity): float
    {
        $movements = StockMovement::where('product_id', $product->id)
            ->where('type', 'in')
            ->where('quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        $totalCost = 0;
        $remainingQuantity = $quantity;

        foreach ($movements as $movement) {
            if ($remainingQuantity <= 0) {
                break;
            }

            // Get available quantity from this movement
            $availableQuantity = $this->getAvailableQuantity($movement);
            
            if ($availableQuantity <= 0) {
                continue;
            }

            $quantityToUse = min($remainingQuantity, $availableQuantity);
            $unitCost = $movement->unit_cost ?? $product->cost ?? 0;
            $totalCost += $quantityToUse * $unitCost;
            $remainingQuantity -= $quantityToUse;
        }

        // If we still need more quantity, use product's default cost
        if ($remainingQuantity > 0) {
            $totalCost += $remainingQuantity * ($product->cost ?? 0);
        }

        return $totalCost;
    }

    /**
     * Calculate LIFO (Last In, First Out) cost.
     */
    protected function calculateLIFO(Product $product, int $quantity): float
    {
        $movements = StockMovement::where('product_id', $product->id)
            ->where('type', 'in')
            ->where('quantity', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalCost = 0;
        $remainingQuantity = $quantity;

        foreach ($movements as $movement) {
            if ($remainingQuantity <= 0) {
                break;
            }

            // Get available quantity from this movement
            $availableQuantity = $this->getAvailableQuantity($movement);
            
            if ($availableQuantity <= 0) {
                continue;
            }

            $quantityToUse = min($remainingQuantity, $availableQuantity);
            $unitCost = $movement->unit_cost ?? $product->cost ?? 0;
            $totalCost += $quantityToUse * $unitCost;
            $remainingQuantity -= $quantityToUse;
        }

        // If we still need more quantity, use product's default cost
        if ($remainingQuantity > 0) {
            $totalCost += $remainingQuantity * ($product->cost ?? 0);
        }

        return $totalCost;
    }

    /**
     * Calculate Average Cost.
     */
    protected function calculateAverageCost(Product $product, int $quantity): float
    {
        $averageCost = $this->getAverageCost($product);
        return $quantity * $averageCost;
    }

    /**
     * Get the current average cost of a product.
     */
    public function getAverageCost(Product $product): float
    {
        $movements = StockMovement::where('product_id', $product->id)
            ->where('type', 'in')
            ->where('quantity', '>', 0)
            ->get();

        if ($movements->isEmpty()) {
            return $product->cost ?? 0;
        }

        $totalCost = 0;
        $totalQuantity = 0;

        foreach ($movements as $movement) {
            $availableQuantity = $this->getAvailableQuantity($movement);
            if ($availableQuantity > 0) {
                $unitCost = $movement->unit_cost ?? $product->cost ?? 0;
                $totalCost += $availableQuantity * $unitCost;
                $totalQuantity += $availableQuantity;
            }
        }

        if ($totalQuantity == 0) {
            return $product->cost ?? 0;
        }

        return $totalCost / $totalQuantity;
    }

    /**
     * Get available quantity from a stock movement (considering outgoing movements).
     */
    protected function getAvailableQuantity(StockMovement $movement): int
    {
        // Get all outgoing movements that reference this incoming movement
        $outgoingMovements = StockMovement::where('product_id', $movement->product_id)
            ->where('type', 'out')
            ->where('created_at', '>=', $movement->created_at)
            ->sum('quantity');

        // For simplicity, we'll use a basic calculation
        // In a more complex system, you'd track which specific incoming movement each outgoing movement consumes
        $totalIncoming = StockMovement::where('product_id', $movement->product_id)
            ->where('type', 'in')
            ->where('created_at', '<=', $movement->created_at)
            ->sum('quantity');

        $totalOutgoing = abs(StockMovement::where('product_id', $movement->product_id)
            ->where('type', 'out')
            ->where('created_at', '<=', $movement->created_at)
            ->sum('quantity'));

        $available = $totalIncoming - $totalOutgoing;
        
        // Return the movement's quantity if it's still available
        return max(0, min($movement->quantity, $available));
    }

    /**
     * Calculate current inventory value.
     */
    public function calculateInventoryValue(Product $product): float
    {
        $method = $product->valuation_method ?? 'fifo';
        $quantity = $product->stock_quantity;

        if ($quantity <= 0) {
            return 0;
        }

        return match($method) {
            'fifo' => $this->calculateFIFO($product, $quantity),
            'lifo' => $this->calculateLIFO($product, $quantity),
            'average_cost' => $this->calculateAverageCost($product, $quantity),
            default => $this->calculateFIFO($product, $quantity),
        };
    }
}


<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductSerialNumber;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class ProductPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->ownsCompanyResource($user, $product);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->ownsCompanyResource($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->ownsCompanyResource($user, $product);
    }

    public function manageBatch(User $user, Product $product, ProductBatch $batch): bool
    {
        return $this->ownsCompanyResource($user, $product)
            && $this->ownsCompanyResource($user, $batch)
            && (int) $batch->product_id === (int) $product->id;
    }

    public function manageSerial(User $user, Product $product, ProductSerialNumber $serial): bool
    {
        return $this->ownsCompanyResource($user, $product)
            && $this->ownsCompanyResource($user, $serial)
            && (int) $serial->product_id === (int) $product->id;
    }
}

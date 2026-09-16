<?php

namespace App\Tenancy\Scopes;

use App\Exceptions\Tenancy\TenantNotBoundException;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the bound tenant.
 *
 * Fail-closed: with no tenant bound the query throws instead of silently
 * returning every tenant's rows. Cross-tenant reads must opt out explicitly
 * via Model::withoutTenancy(), which makes each one greppable.
 */
final class TenantScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->has()) {
            throw TenantNotBoundException::forQuery($model);
        }

        $builder->where($model->qualifyColumn('tenant_id'), $context->current()->getKey());
    }
}

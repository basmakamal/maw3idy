<?php

namespace App\Tenancy\Concerns;

use App\Exceptions\Tenancy\TenantMismatchException;
use App\Exceptions\Tenancy\TenantNotBoundException;
use App\Models\Tenant;
use App\Tenancy\Scopes\TenantScope;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by a tenant.
 *
 * - Reads are constrained to the bound tenant (see TenantScope).
 * - Creates get tenant_id from the bound tenant, and may not target another one.
 * - Updates may never move a record to a different tenant.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);
            $given = $model->getAttribute('tenant_id');

            if ($given === null) {
                if (! $context->has()) {
                    throw TenantNotBoundException::forCreate($model);
                }

                $model->setAttribute('tenant_id', $context->current()->getKey());

                return;
            }

            if ($context->has() && (string) $given !== (string) $context->current()->getKey()) {
                throw TenantMismatchException::forCreate($model, $context->current()->getKey(), $given);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw TenantMismatchException::forReassignment($model);
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Query across all tenants. Every call site is an explicit, reviewable decision.
     *
     * @return Builder<self>
     */
    public static function withoutTenancy(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}

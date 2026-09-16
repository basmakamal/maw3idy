<?php

namespace App\Exceptions\Tenancy;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when tenant-owned data is touched while no tenant is bound.
 *
 * This is a programming error, never a user error: the request should have
 * gone through the tenant domain, or the code should have used
 * TenantContext::runAs() or Model::withoutTenancy() explicitly.
 */
final class TenantNotBoundException extends RuntimeException
{
    public static function noContext(): self
    {
        return new self('No tenant is bound to the current context.');
    }

    public static function forQuery(Model $model): self
    {
        return new self(sprintf(
            'Refusing to query %s without a bound tenant. Bind one with TenantContext::set() or runAs(), '.
            'or opt out explicitly with %s::withoutTenancy().',
            $model::class,
            class_basename($model),
        ));
    }

    public static function forCreate(Model $model): self
    {
        return new self(sprintf(
            'Refusing to create %s without a bound tenant and without an explicit tenant_id.',
            $model::class,
        ));
    }
}

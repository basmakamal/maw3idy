<?php

namespace App\Exceptions\Tenancy;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a write would cross a tenant boundary: creating a record for
 * tenant B while tenant A is bound, or moving a record between tenants.
 */
final class TenantMismatchException extends RuntimeException
{
    public static function forCreate(Model $model, int|string $bound, int|string $given): self
    {
        return new self(sprintf(
            'Refusing to create %s for tenant %s while tenant %s is bound.',
            $model::class,
            $given,
            $bound,
        ));
    }

    public static function forReassignment(Model $model): self
    {
        return new self(sprintf(
            'Refusing to move %s #%s to another tenant; records never change tenant.',
            $model::class,
            $model->getKey(),
        ));
    }
}

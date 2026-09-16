<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/**
 * Every user of the tenant may see the catalogue; only the owner shapes it.
 * Tenant isolation itself is enforced by the query scope, not here.
 */
final class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Service $service): bool
    {
        return $user->isOwner() && (int) $user->tenant_id === (int) $service->tenant_id;
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }
}

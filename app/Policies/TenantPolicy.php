<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

final class TenantPolicy
{
    /**
     * Only the owner may change the business's settings, and only for the
     * business they belong to.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->isOwner() && (int) $user->tenant_id === (int) $tenant->getKey();
    }
}

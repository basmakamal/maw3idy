<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

final class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Staff $staff): bool
    {
        return (int) $user->tenant_id === (int) $staff->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Staff $staff): bool
    {
        return $user->isOwner() && (int) $user->tenant_id === (int) $staff->tenant_id;
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $this->update($user, $staff);
    }
}

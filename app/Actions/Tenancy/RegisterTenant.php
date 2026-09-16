<?php

namespace App\Actions\Tenancy;

use App\Data\TenantRegistrationData;
use App\Enums\UserRole;
use App\Events\TenantRegistered;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Opens a new business account: the tenant row plus its owner, atomically.
 *
 * Runs from the central domain where no tenant is bound, so the owner is
 * created inside TenantContext::runAs() rather than by bypassing the scope.
 */
final class RegisterTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(TenantRegistrationData $data): Tenant
    {
        /** @var array{Tenant, User} $result */
        $result = DB::transaction(function () use ($data): array {
            $tenant = Tenant::create([
                'name' => $data->businessName,
                'slug' => $data->slug,
                'timezone' => $data->timezone,
                'locale' => $data->locale,
                'settings' => [],
            ]);

            $owner = $this->context->runAs($tenant, function () use ($data): User {
                $owner = new User([
                    'name' => $data->ownerName,
                    'email' => $data->ownerEmail,
                    'password' => $data->ownerPassword,
                ]);
                $owner->role = UserRole::Owner;
                $owner->save();

                return $owner;
            });

            return [$tenant, $owner];
        });

        [$tenant, $owner] = $result;

        // Dispatched after the transaction so listeners never see uncommitted rows.
        TenantRegistered::dispatch($tenant, $owner);

        return $tenant;
    }
}

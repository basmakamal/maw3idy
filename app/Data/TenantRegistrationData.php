<?php

namespace App\Data;

/**
 * Everything needed to open a new business account: the tenant and its owner.
 * Built by the form request, consumed by the RegisterTenant action, so the
 * action never touches HTTP input.
 */
final readonly class TenantRegistrationData
{
    public function __construct(
        public string $businessName,
        public string $slug,
        public string $timezone,
        public string $locale,
        public string $ownerName,
        public string $ownerEmail,
        public string $ownerPassword,
    ) {}
}

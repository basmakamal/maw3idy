<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * A customer's request to book: which service, with whom (null = anyone who
 * can), when (a UTC instant), and how to reach them.
 */
final readonly class BookingRequestData
{
    public function __construct(
        public int $serviceId,
        public ?int $staffId,
        public CarbonImmutable $start,
        public string $customerName,
        public string $customerPhone,
        public ?string $customerEmail = null,
    ) {}
}

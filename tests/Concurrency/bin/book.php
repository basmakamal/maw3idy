<?php

/*
 * Books one slot from a separate PHP process, so tests can fire several of these
 * at the same instant and prove that only one wins.
 *
 * Usage: php book.php <tenant-slug> <service-id> <staff-id|any> <start-iso-utc> <customer-name> [<go-at-unix-ms>]
 * Prints "OK <reference>", "TAKEN" or "ERROR <class>: <message>".
 */

use App\Actions\Booking\CreateBooking;
use App\Data\BookingRequestData;
use App\Exceptions\Booking\SlotUnavailableException;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $slug, $serviceId, $staffId, $start, $customer] = $argv;
$goAt = isset($argv[6]) ? (int) $argv[6] : null;

// Spin until the agreed instant so every process hits the database together.
if ($goAt !== null) {
    while ((int) (microtime(true) * 1000) < $goAt) {
        usleep(200);
    }
}

$tenant = Tenant::query()->where('slug', $slug)->firstOrFail();

try {
    $booking = $app->make(TenantContext::class)->runAs($tenant, fn () => $app->make(CreateBooking::class)->handle(new BookingRequestData(
        serviceId: (int) $serviceId,
        staffId: $staffId === 'any' ? null : (int) $staffId,
        start: CarbonImmutable::parse($start, 'UTC'),
        customerName: $customer,
        customerPhone: '+966500000000',
    )));

    fwrite(STDOUT, "OK {$booking->reference}\n");
    exit(0);
} catch (SlotUnavailableException) {
    fwrite(STDOUT, "TAKEN\n");
    exit(2);
} catch (Throwable $e) {
    fwrite(STDOUT, 'ERROR '.$e::class.': '.$e->getMessage()."\n");
    exit(1);
}

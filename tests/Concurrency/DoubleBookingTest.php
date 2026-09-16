<?php

use App\Enums\Weekday;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Symfony\Component\Process\Process;

/*
 * Real concurrency: separate PHP processes, separate database connections,
 * released at the same millisecond against the same slot. Data is committed
 * (DatabaseTruncation, not a rolled-back transaction) so the children see it.
 */

beforeEach(function () {
    $this->tenant = bindTenant(Tenant::factory()->create(['slug' => 'race', 'timezone' => 'Asia/Riyadh']));
    $this->service = Service::factory()->lasting(60)->create();
    $this->sara = Staff::factory()->create(['name' => 'Sara']);
    $this->sara->services()->attach($this->service);

    // Open every weekday so "next Monday 10:00" is always bookable.
    foreach (Weekday::cases() as $weekday) {
        Schedule::factory()->for($this->sara)->on($weekday, '09:00', '17:00')->create();
    }

    $this->start = CarbonImmutable::now('Asia/Riyadh')->addWeek()->next('Monday')->setTime(10, 0)->utc();
});

/**
 * @return list<string> one output line per process
 */
function raceFor(int $processes, string $slug, int $serviceId, string $staffId, CarbonImmutable $start): array
{
    $goAt = (int) (microtime(true) * 1000) + 1500;

    $running = collect(range(1, $processes))->map(function (int $i) use ($slug, $serviceId, $staffId, $start, $goAt) {
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Concurrency/bin/book.php'),
            $slug,
            (string) $serviceId,
            $staffId,
            $start->toIso8601String(),
            "Customer $i",
            (string) $goAt,
        ], base_path(), null, null, 60);

        $process->start();

        return $process;
    });

    return $running->map(function (Process $process) {
        $process->wait();

        return trim($process->getOutput().$process->getErrorOutput());
    })->all();
}

it('lets exactly one of several simultaneous requests book the same slot', function () {
    $results = raceFor(4, 'race', $this->service->id, (string) $this->sara->id, $this->start);

    $wins = array_filter($results, fn (string $line) => str_starts_with($line, 'OK '));
    $taken = array_filter($results, fn (string $line) => $line === 'TAKEN');

    expect($results)->toHaveCount(4)
        ->and($wins)->toHaveCount(1, 'Expected one winner, got: '.implode(' | ', $results))
        ->and($taken)->toHaveCount(3, 'Expected three losers, got: '.implode(' | ', $results))
        ->and(Booking::withoutTenancy()->confirmed()->count())->toBe(1);
});

it('gives "anyone available" requests different staff instead of the same one', function () {
    $omar = Staff::factory()->create(['name' => 'Omar']);
    $omar->services()->attach($this->service);
    foreach (Weekday::cases() as $weekday) {
        Schedule::factory()->for($omar)->on($weekday, '09:00', '17:00')->create();
    }

    $results = raceFor(3, 'race', $this->service->id, 'any', $this->start);

    $wins = array_filter($results, fn (string $line) => str_starts_with($line, 'OK '));

    expect($wins)->toHaveCount(2, 'Two staff, so two winners expected; got: '.implode(' | ', $results))
        ->and(array_filter($results, fn (string $line) => $line === 'TAKEN'))->toHaveCount(1)
        ->and(Booking::withoutTenancy()->confirmed()->pluck('staff_id')->sort()->values()->all())
        ->toBe(collect([$this->sara->id, $omar->id])->sort()->values()->all());
});

<?php

declare(strict_types=1);

/*
 * Enforces coverage where it matters rather than only in aggregate.
 *
 * One overall floor keeps the suite honest, and two per-area floors protect
 * the parts of this product that would hurt most if they broke quietly: the
 * availability engine and the tenant isolation layer. A high overall number
 * can hide an untested engine, which is exactly the failure this guards.
 *
 * Usage: php scripts/coverage-gate.php coverage.xml
 */

$file = $argv[1] ?? 'coverage.xml';

if (! is_file($file)) {
    fwrite(STDERR, "Coverage report not found: {$file}\n");
    exit(1);
}

/** @var array<string, int> $floors path fragment => minimum percentage */
$floors = [
    'app/Booking/Availability' => 95,
    'app/Tenancy' => 90,
];

$overallFloor = 80;

$xml = simplexml_load_file($file);

if ($xml === false) {
    fwrite(STDERR, "Could not parse {$file}\n");
    exit(1);
}

/** @var array<string, array{covered: int, total: int}> $areas */
$areas = [];
$overall = ['covered' => 0, 'total' => 0];

foreach ($xml->xpath('//file') ?: [] as $fileNode) {
    $metrics = $fileNode->metrics;

    if ($metrics === null) {
        continue;
    }

    $statements = (int) $metrics['statements'];
    $covered = (int) $metrics['coveredstatements'];
    $path = str_replace('\\', '/', (string) $fileNode['name']);

    $overall['covered'] += $covered;
    $overall['total'] += $statements;

    foreach (array_keys($floors) as $area) {
        if (! str_contains($path, $area)) {
            continue;
        }

        $areas[$area] ??= ['covered' => 0, 'total' => 0];
        $areas[$area]['covered'] += $covered;
        $areas[$area]['total'] += $statements;
    }
}

$percentage = static fn (array $counts): float => $counts['total'] === 0
    ? 100.0
    : round($counts['covered'] / $counts['total'] * 100, 2);

$failed = false;
$report = [];

$overallPercentage = $percentage($overall);
$report[] = sprintf('%-28s %6.2f%%  (floor %d%%)', 'overall', $overallPercentage, $overallFloor);

if ($overallPercentage < $overallFloor) {
    $failed = true;
}

foreach ($floors as $area => $floor) {
    if (! isset($areas[$area])) {
        $report[] = sprintf('%-28s %s', $area, 'NOT MEASURED');
        $failed = true;

        continue;
    }

    $areaPercentage = $percentage($areas[$area]);
    $report[] = sprintf('%-28s %6.2f%%  (floor %d%%)', $area, $areaPercentage, $floor);

    if ($areaPercentage < $floor) {
        $failed = true;
    }
}

echo implode("\n", $report), "\n";

if ($failed) {
    fwrite(STDERR, "\nCoverage is below one of the floors above.\n");
    exit(1);
}

echo "\nCoverage floors met.\n";
exit(0);

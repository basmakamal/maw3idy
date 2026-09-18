#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Compares two OpenAPI specs by shape, not by bytes.
 *
 * The committed reference cannot be byte-compared: some parameter
 * descriptions are built from validation rules, and a rule like
 * "date after_or_equal:today" writes today's date into the text, so the file
 * would differ every day for no reason.
 *
 * What must not drift silently is the API's shape. This compares the set of
 * operations, and for each one its parameters, request body fields and
 * response status codes. Adding an endpoint, renaming a parameter or
 * introducing a status code without regenerating the docs fails the build;
 * the calendar moving on does not.
 *
 * Usage: php scripts/docs-drift.php committed.yaml regenerated.yaml
 */

use Symfony\Component\Yaml\Yaml;

require __DIR__.'/../vendor/autoload.php';

$committedPath = $argv[1] ?? null;
$currentPath = $argv[2] ?? null;

if ($committedPath === null || $currentPath === null) {
    fwrite(STDERR, "Usage: php scripts/docs-drift.php committed.yaml regenerated.yaml\n");
    exit(2);
}

foreach ([$committedPath, $currentPath] as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Spec not found: {$path}\n");
        exit(2);
    }
}

/**
 * The shape of every operation: parameters, body fields and status codes.
 *
 * @return array<string, array{parameters: list<string>, body: list<string>, responses: list<string>}>
 */
$shape = static function (string $path): array {
    /** @var array<string, mixed> $spec */
    $spec = Yaml::parseFile($path);

    /** @var array<string, array<string, mixed>> $paths */
    $paths = $spec['paths'] ?? [];

    $operations = [];

    foreach ($paths as $route => $methods) {
        /** @var list<array<string, mixed>> $sharedParameters */
        $sharedParameters = $methods['parameters'] ?? [];

        foreach ($methods as $method => $operation) {
            if (! is_array($operation) || in_array($method, ['parameters', 'summary', 'description'], true)) {
                continue;
            }

            /** @var list<array<string, mixed>> $parameters */
            $parameters = array_merge($sharedParameters, $operation['parameters'] ?? []);

            $names = array_map(
                static fn (array $parameter): string => ($parameter['in'] ?? '?').':'.($parameter['name'] ?? '?'),
                $parameters,
            );
            sort($names);

            /** @var array<string, mixed> $properties */
            $properties = $operation['requestBody']['content']['application/json']['schema']['properties'] ?? [];
            $body = array_keys($properties);
            sort($body);

            $responses = array_map('strval', array_keys($operation['responses'] ?? []));
            sort($responses);

            $operations[strtoupper($method).' '.$route] = [
                'parameters' => array_values(array_unique($names)),
                'body' => $body,
                'responses' => $responses,
            ];
        }
    }

    ksort($operations);

    return $operations;
};

$committed = $shape($committedPath);
$current = $shape($currentPath);

$problems = [];

foreach (array_diff(array_keys($current), array_keys($committed)) as $added) {
    $problems[] = "undocumented operation: {$added}";
}

foreach (array_diff(array_keys($committed), array_keys($current)) as $removed) {
    $problems[] = "operation documented but no longer routed: {$removed}";
}

foreach ($current as $operation => $shapeOfOperation) {
    if (! isset($committed[$operation])) {
        continue;
    }

    foreach (['parameters', 'body', 'responses'] as $facet) {
        $before = $committed[$operation][$facet];
        $after = $shapeOfOperation[$facet];

        if ($before === $after) {
            continue;
        }

        $problems[] = sprintf(
            "%s: %s changed\n    committed:   %s\n    regenerated: %s",
            $operation,
            $facet,
            $before === [] ? '(none)' : implode(', ', $before),
            $after === [] ? '(none)' : implode(', ', $after),
        );
    }
}

printf("Compared %d operations.\n", count($current));

if ($problems !== []) {
    fwrite(STDERR, "\nThe committed API reference no longer matches the code:\n\n  - ".implode("\n  - ", $problems)."\n\nRun 'composer docs' and commit public/docs.\n");
    exit(1);
}

echo "The committed API reference matches the code.\n";
exit(0);

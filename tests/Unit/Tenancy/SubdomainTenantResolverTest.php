<?php

use App\Tenancy\Resolvers\SubdomainTenantResolver;

it('extracts the tenant label from a tenant host', function (string $host, ?string $expected) {
    $resolver = new SubdomainTenantResolver('maw3idy.test');

    expect($resolver->slugFromHost($host))->toBe($expected);
})->with([
    'plain tenant' => ['acme.maw3idy.test', 'acme'],
    'with digits and hyphens' => ['salon-24.maw3idy.test', 'salon-24'],
    'uppercase host' => ['ACME.Maw3idy.TEST', 'acme'],
    'central domain itself' => ['maw3idy.test', null],
    'nested subdomain' => ['a.b.maw3idy.test', null],
    'foreign domain' => ['acme.example.com', null],
    'lookalike suffix' => ['acme.notmaw3idy.test', null],
    'leading hyphen' => ['-acme.maw3idy.test', null],
    'trailing hyphen' => ['acme-.maw3idy.test', null],
    'too long' => [str_repeat('a', 64).'.maw3idy.test', null],
    'max length' => [str_repeat('a', 63).'.maw3idy.test', str_repeat('a', 63)],
]);

it('is case-insensitive about the central domain it was configured with', function () {
    $resolver = new SubdomainTenantResolver('Maw3idy.TEST');

    expect($resolver->slugFromHost('acme.maw3idy.test'))->toBe('acme');
});

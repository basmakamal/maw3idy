<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Generates the API reference.
 *
 * Scribe reads the form requests to describe parameters, and those rules ask
 * the bound tenant for its timezone and id. A console command has no request
 * and therefore no tenant, so one is bound for the duration of the run
 * (ADR-020). Nothing is written to the database.
 */
class GenerateApiDocsCommand extends Command
{
    protected $signature = 'docs:api {--slug=demo : The tenant whose settings shape the examples}';

    protected $description = 'Generate the API reference into public/docs';

    public function handle(TenantContext $context): int
    {
        $tenant = $this->tenant();

        if ($tenant->getKey() === null) {
            $this->line('Documenting with an in-memory example tenant.');
        }

        return $context->runAs($tenant, fn (): int => $this->call('scribe:generate'));
    }

    /**
     * A tenant to answer the form requests with.
     *
     * Prefers the demo tenant, because Scribe asks the database for realistic
     * ids where a route binds a model, and CI regenerates the reference
     * against the same seeded data to diff it. Falling back to an unsaved
     * example tenant keeps the command usable on a machine with no database,
     * with the caveat that a few ids in the output will then be invented.
     */
    private function tenant(): Tenant
    {
        /** @var string $slug */
        $slug = $this->option('slug');

        try {
            $tenant = Tenant::query()->where('slug', $slug)->first()
                ?? Tenant::query()->orderBy('id')->first();
        } catch (Throwable $e) {
            $this->line('No database available ('.class_basename($e).'); documenting without one.');

            $tenant = null;
        }

        return $tenant ?? new Tenant([
            'name' => 'Demo Salon',
            'slug' => 'demo',
            'timezone' => 'Asia/Riyadh',
            'locale' => 'en',
        ]);
    }
}

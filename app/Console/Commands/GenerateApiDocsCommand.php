<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

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
        /** @var string $slug */
        $slug = $this->option('slug');

        $tenant = Tenant::query()->where('slug', $slug)->first()
            ?? Tenant::query()->orderBy('id')->first()
            // Never saved: enough of a tenant for the form requests to answer.
            ?? new Tenant(['name' => 'Demo Salon', 'slug' => 'demo', 'timezone' => 'Asia/Riyadh', 'locale' => 'en']);

        if ($tenant->getKey() === null) {
            $this->warn('No tenant in the database; documenting with an in-memory example tenant.');
        }

        return $context->runAs($tenant, fn (): int => $this->call('scribe:generate'));
    }
}

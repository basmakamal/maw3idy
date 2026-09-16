<?php

namespace App\Http\Controllers\Central;

use App\Actions\Tenancy\RegisterTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\RegisterTenantRequest;
use App\Support\Localization;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class RegisterTenantController extends Controller
{
    public function create(): View
    {
        /** @var list<string> $locales */
        $locales = config('tenancy.supported_locales');

        return view('central.register', [
            'timezones' => DateTimeZone::listIdentifiers(),
            'defaultTimezone' => config('tenancy.default_timezone'),
            'locales' => collect($locales)->mapWithKeys(fn (string $code) => [$code => Localization::name($code)]),
        ]);
    }

    public function store(RegisterTenantRequest $request, RegisterTenant $registerTenant): RedirectResponse
    {
        $tenant = $registerTenant->handle($request->toData());

        // Sessions are host-only, so the new owner signs in on their own subdomain.
        return redirect()->route('tenant.login', ['tenant' => $tenant->slug, 'registered' => 1]);
    }
}

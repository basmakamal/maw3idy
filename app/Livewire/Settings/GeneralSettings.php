<?php

namespace App\Livewire\Settings;

use App\Support\Localization;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Business-level settings: name, timezone and language. Owner only.
 *
 * The timezone is the reference for every schedule and booking display (storage
 * stays UTC); the locale drives the dashboard language and text direction.
 */
class GeneralSettings extends Component
{
    public string $name = '';

    public string $timezone = '';

    public string $locale = '';

    public function mount(): void
    {
        $this->authorize('update', tenant());

        $this->fill(tenant()->only(['name', 'timezone', 'locale']));
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'locale' => ['required', 'string', Rule::in(config('tenancy.supported_locales'))],
        ];
    }

    public function save(): void
    {
        $this->authorize('update', tenant());

        tenant()->update($this->validate());

        session()->flash('status', __('Settings saved.'));

        // Full reload so a language change also flips the page direction.
        $this->redirectRoute('tenant.settings');
    }

    public function render(): View
    {
        /** @var list<string> $locales */
        $locales = config('tenancy.supported_locales');

        return view('livewire.settings.general-settings', [
            'timezones' => DateTimeZone::listIdentifiers(),
            'locales' => collect($locales)->mapWithKeys(fn (string $code) => [$code => Localization::name($code)]),
        ]);
    }
}

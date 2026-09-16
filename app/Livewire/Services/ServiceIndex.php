<?php

namespace App\Livewire\Services;

use App\Livewire\Forms\ServiceForm;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The service catalogue: what customers can book. Everyone in the tenant can
 * read it; the owner adds, edits and (de)activates.
 */
class ServiceIndex extends Component
{
    public ServiceForm $form;

    public bool $editing = false;

    public function create(): void
    {
        $this->authorize('create', Service::class);

        $this->form->reset();
        $this->form->service = null;
        $this->editing = true;
    }

    public function edit(int $serviceId): void
    {
        $service = Service::query()->findOrFail($serviceId);
        $this->authorize('update', $service);

        $this->form->setService($service);
        $this->editing = true;
    }

    public function save(): void
    {
        $this->form->service === null
            ? $this->authorize('create', Service::class)
            : $this->authorize('update', $this->form->service);

        $this->form->save();

        $this->editing = false;
        $this->form->reset();
        unset($this->services);

        session()->flash('status', __('Service saved.'));
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->form->reset();
    }

    public function toggleActive(int $serviceId): void
    {
        $service = Service::query()->findOrFail($serviceId);
        $this->authorize('update', $service);

        $service->update(['active' => ! $service->active]);
        unset($this->services);
    }

    /**
     * @return EloquentCollection<int, Service>
     */
    #[Computed]
    public function services(): EloquentCollection
    {
        return Service::query()->with('staff')->orderBy('name')->get();
    }

    /**
     * @return EloquentCollection<int, Staff>
     */
    #[Computed]
    public function staffOptions(): EloquentCollection
    {
        return Staff::query()->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.services.service-index', [
            'canManage' => auth()->user()?->can('create', Service::class) ?? false,
            'currency' => config('booking.currency'),
        ]);
    }
}

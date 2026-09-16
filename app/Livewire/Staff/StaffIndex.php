<?php

namespace App\Livewire\Staff;

use App\Livewire\Forms\StaffForm;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class StaffIndex extends Component
{
    public StaffForm $form;

    public bool $editing = false;

    public function create(): void
    {
        $this->authorize('create', Staff::class);

        $this->form->reset();
        $this->form->staff = null;
        $this->editing = true;
    }

    public function edit(int $staffId): void
    {
        $staff = Staff::query()->findOrFail($staffId);
        $this->authorize('update', $staff);

        $this->form->setStaff($staff);
        $this->editing = true;
    }

    public function save(): void
    {
        $this->form->staff === null
            ? $this->authorize('create', Staff::class)
            : $this->authorize('update', $this->form->staff);

        $this->form->save();

        $this->editing = false;
        $this->form->reset();
        unset($this->members);

        session()->flash('status', __('Staff member saved.'));
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->form->reset();
    }

    public function toggleActive(int $staffId): void
    {
        $staff = Staff::query()->findOrFail($staffId);
        $this->authorize('update', $staff);

        $staff->update(['active' => ! $staff->active]);
        unset($this->members);
    }

    /**
     * @return EloquentCollection<int, Staff>
     */
    #[Computed]
    public function members(): EloquentCollection
    {
        return Staff::query()->with(['services', 'schedules'])->orderBy('name')->get();
    }

    /**
     * @return EloquentCollection<int, Service>
     */
    #[Computed]
    public function serviceOptions(): EloquentCollection
    {
        return Service::query()->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.staff.staff-index', [
            'canManage' => auth()->user()?->can('create', Staff::class) ?? false,
        ]);
    }
}

<?php

namespace App\Livewire\Forms;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StaffForm extends Form
{
    public ?Staff $staff = null;

    public string $name = '';

    public string $email = '';

    public bool $active = true;

    /** @var list<int|string> */
    public array $serviceIds = [];

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'active' => ['boolean'],
            'serviceIds' => ['array'],
            'serviceIds.*' => ['integer', Rule::exists('services', 'id')->where('tenant_id', tenant()->getKey())],
        ];
    }

    public function setStaff(Staff $staff): void
    {
        $this->staff = $staff;
        $this->name = $staff->name;
        $this->email = (string) $staff->email;
        $this->active = $staff->active;
        $this->serviceIds = $staff->services()->pluck('services.id')->all();
    }

    public function save(): Staff
    {
        $validated = $this->validate();

        return DB::transaction(function () use ($validated): Staff {
            $staff = $this->staff ?? new Staff;

            $staff->fill([
                'name' => $validated['name'],
                'email' => $validated['email'] !== '' ? $validated['email'] : null,
                'active' => (bool) $validated['active'],
            ])->save();

            $staff->services()->sync(array_map('intval', $validated['serviceIds']));

            return $staff;
        });
    }
}

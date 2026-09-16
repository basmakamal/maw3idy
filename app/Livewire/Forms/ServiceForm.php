<?php

namespace App\Livewire\Forms;

use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServiceForm extends Form
{
    public ?Service $service = null;

    public string $name = '';

    public string $description = '';

    public int|string $duration_minutes = 30;

    public int|string $buffer_after_minutes = 0;

    public string $price = '0.00';

    public bool $active = true;

    /** @var list<int|string> */
    public array $staffIds = [];

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'buffer_after_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'active' => ['boolean'],
            'staffIds' => ['array'],
            // exists() is a raw query, so the tenant scope does not apply: constrain it by hand.
            'staffIds.*' => ['integer', Rule::exists('staff', 'id')->where('tenant_id', tenant()->getKey())],
        ];
    }

    public function setService(Service $service): void
    {
        $this->service = $service;
        $this->name = $service->name;
        $this->description = (string) $service->description;
        $this->duration_minutes = $service->duration_minutes;
        $this->buffer_after_minutes = $service->buffer_after_minutes;
        $this->price = $service->price;
        $this->active = $service->active;
        $this->staffIds = $service->staff()->pluck('staff.id')->all();
    }

    public function save(): Service
    {
        $validated = $this->validate();

        return DB::transaction(function () use ($validated): Service {
            $service = $this->service ?? new Service;

            $service->fill([
                'name' => $validated['name'],
                'description' => $validated['description'] !== '' ? $validated['description'] : null,
                'duration_minutes' => (int) $validated['duration_minutes'],
                'buffer_after_minutes' => (int) $validated['buffer_after_minutes'],
                'price' => number_format((float) $validated['price'], 2, '.', ''),
                'active' => (bool) $validated['active'],
            ])->save();

            $service->staff()->sync(array_map('intval', $validated['staffIds']));

            return $service;
        });
    }
}

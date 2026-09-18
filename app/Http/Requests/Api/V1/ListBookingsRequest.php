<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'staff_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', Rule::in(array_column(BookingStatus::cases(), 'value'))],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Documentation for the generated API reference. Examples are fixed rather
     * than generated, so the committed reference is stable.
     *
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'from' => ['description' => 'Only appointments starting at or after this instant.', 'example' => '2026-10-05T00:00:00Z'],
            'to' => ['description' => 'Only appointments starting at or before this instant.', 'example' => '2026-10-12T00:00:00Z'],
            'staff_id' => ['description' => 'Only this staff member\'s appointments.', 'example' => 1],
            'status' => ['description' => 'Either `confirmed` or `cancelled`.', 'example' => 'confirmed'],
            'per_page' => ['description' => 'Results per page, 1 to 100.', 'example' => 25],
        ];
    }

    public function startingFrom(): ?CarbonImmutable
    {
        return $this->has('from') ? CarbonImmutable::parse($this->string('from')->toString())->utc() : null;
    }

    public function startingUntil(): ?CarbonImmutable
    {
        return $this->has('to') ? CarbonImmutable::parse($this->string('to')->toString())->utc() : null;
    }

    public function staffId(): ?int
    {
        return $this->has('staff_id') ? $this->integer('staff_id') : null;
    }

    public function status(): ?BookingStatus
    {
        return $this->has('status') ? BookingStatus::from($this->string('status')->toString()) : null;
    }

    public function perPage(): int
    {
        return $this->has('per_page') ? $this->integer('per_page') : 25;
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AvailabilityRequest extends FormRequest
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
        $today = CarbonImmutable::now(tenant()->timezone)->startOfDay();

        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.$today->addDays((int) config('booking.window_days'))->toDateString(),
            ],
            // Scoped by hand: the exists rule runs as a raw query, outside the tenant scope.
            'staff_id' => ['sometimes', 'integer', Rule::exists('staff', 'id')->where('tenant_id', tenant()->getKey())],
        ];
    }

    /**
     * Documentation for the generated API reference.
     *
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'date' => [
                'description' => 'The day to check, in the business\'s timezone. Today or later, and within the booking window.',
                'example' => '2026-10-05',
            ],
            'staff_id' => [
                'description' => 'Limit the answer to one staff member. Omit to see every time anyone could take.',
                'example' => 1,
            ],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Data\BookingRequestData;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('customer_phone'))) {
            $this->merge(['customer_phone' => Phone::normalize($this->string('customer_phone')->toString())]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('tenant_id', tenant()->getKey())],
            'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->where('tenant_id', tenant()->getKey())],
            'starts_at' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:'.Phone::PATTERN],
            'customer_email' => ['nullable', 'string', 'email:rfc', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_phone.regex' => __('Enter a phone number with 8 to 15 digits, e.g. 05xxxxxxxx or +9665xxxxxxxx.'),
        ];
    }

    public function toData(): BookingRequestData
    {
        return new BookingRequestData(
            serviceId: $this->integer('service_id'),
            staffId: $this->input('staff_id') !== null ? $this->integer('staff_id') : null,
            // Whatever offset the client sent, the domain works in UTC.
            start: CarbonImmutable::parse($this->string('starts_at')->toString())->utc(),
            customerName: $this->string('customer_name')->toString(),
            customerPhone: $this->string('customer_phone')->toString(),
            customerEmail: $this->input('customer_email') !== null ? $this->string('customer_email')->toString() : null,
        );
    }
}

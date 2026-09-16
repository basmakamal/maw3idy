<?php

namespace App\Http\Requests\Central;

use App\Data\TenantRegistrationData;
use App\Tenancy\Resolvers\SubdomainTenantResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Subdomains are DNS labels and case-insensitive: normalise before validating
     * so "ACME" and "acme" cannot both be registered.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::lower(trim((string) $this->input('slug'))),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:63',
                'regex:'.SubdomainTenantResolver::SLUG_PATTERN,
                Rule::notIn(config('tenancy.reserved_subdomains')),
                Rule::unique('tenants', 'slug'),
            ],
            'timezone' => ['required', 'string', 'timezone:all'],
            'locale' => ['required', 'string', Rule::in(config('tenancy.supported_locales'))],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('Use only lowercase letters, numbers and hyphens, starting and ending with a letter or number.'),
            'slug.not_in' => __('This address is reserved. Please choose another.'),
            'slug.unique' => __('This address is already taken.'),
        ];
    }

    public function toData(): TenantRegistrationData
    {
        return new TenantRegistrationData(
            businessName: $this->string('business_name')->toString(),
            slug: $this->string('slug')->toString(),
            timezone: $this->string('timezone')->toString(),
            locale: $this->string('locale')->toString(),
            ownerName: $this->string('name')->toString(),
            ownerEmail: $this->string('email')->toString(),
            ownerPassword: $this->string('password')->toString(),
        );
    }
}

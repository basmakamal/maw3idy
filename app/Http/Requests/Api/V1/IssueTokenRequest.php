<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IssueTokenRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', Rule::in(TokenAbility::values())],
        ];
    }

    /**
     * Documentation for the generated API reference.
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'email' => ['description' => 'A staff account at this business.', 'example' => 'owner@demo.test'],
            'password' => ['description' => 'That account\'s password.', 'example' => 'password'],
            'device_name' => ['description' => 'What this token is for. Shown when reviewing tokens.', 'example' => 'Front desk iPad'],
            'abilities' => [
                'description' => 'What the token may do. Any of `services:read`, `bookings:read`, `bookings:write`. Defaults to the two read abilities.',
                'example' => ['bookings:write'],
            ],
            // The item example is pinned too: without it the `in` rule makes
            // the generator pick an allowed value at random, and the committed
            // reference would differ on every regeneration.
            'abilities.*' => [
                'description' => 'One ability.',
                'example' => 'bookings:write',
            ],
        ];
    }

    /**
     * Least privilege by default: a caller that asks for nothing gets read
     * access only, and must say so explicitly to be able to write.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        /** @var list<string>|null $requested */
        $requested = $this->input('abilities');

        return $requested === null || $requested === []
            ? [TokenAbility::ReadServices->value, TokenAbility::ReadBookings->value]
            : array_values(array_unique($requested));
    }
}

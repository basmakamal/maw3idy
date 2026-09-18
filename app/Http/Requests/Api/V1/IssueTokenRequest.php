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

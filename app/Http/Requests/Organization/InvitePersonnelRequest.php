<?php

namespace App\Http\Requests\Organization;

use App\Enums\OrganizationRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvitePersonnelRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A person belongs to at most one organization and an existing seeker
     * account cannot become personnel, so the email must be new.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(OrganizationRole::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email already has a JoBauko account. Each person can belong to only one organization; use a different email.',
        ];
    }
}

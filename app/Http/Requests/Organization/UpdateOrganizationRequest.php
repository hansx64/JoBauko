<?php

namespace App\Http\Requests\Organization;

use App\Enums\OrganizationType;
use App\Models\Barangay;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'official_email' => ['required', 'string', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'regex:/^[0-9+()\-\s]{7,20}$/'],
            'office_address' => ['required', 'string', 'max:255'],
            'barangay_id' => ['nullable', 'integer', Rule::exists(Barangay::class, 'id')],
            'description' => ['nullable', 'string', 'max:3000'],
            'representative_name' => ['required', 'string', 'max:255'],
            'representative_position' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:5120'],
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
            'contact_number.regex' => 'Enter a valid phone number.',
            'documents.*.mimes' => 'Documents must be PDF, JPG or PNG files.',
            'documents.*.max' => 'Each document must be 5 MB or smaller.',
        ];
    }
}

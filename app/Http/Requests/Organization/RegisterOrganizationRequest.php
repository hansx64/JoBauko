<?php

namespace App\Http\Requests\Organization;

use App\Enums\AccountType;
use App\Enums\OrganizationType;
use App\Models\Barangay;
use App\Models\User;
use App\Support\MobileNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterOrganizationRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (filled($this->input('mobile_number'))) {
            $this->merge(['mobile_number' => MobileNumber::normalize($this->input('mobile_number'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Organization
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'official_email' => ['required', 'string', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'regex:/^[0-9+()\-\s]{7,20}$/'],
            'office_address' => ['required', 'string', 'max:255'],
            'barangay_id' => ['nullable', 'integer', Rule::exists(Barangay::class, 'id')],
            'description' => ['nullable', 'string', 'max:3000'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:5120'],

            // Authorized representative, who becomes the organization administrator
            'representative_name' => ['required', 'string', 'max:255'],
            'representative_position' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'mobile_number' => ['nullable', 'string', 'regex:/^09\d{9}$/', MobileNumber::uniqueFor(AccountType::Organization)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'privacy_consent' => ['accepted'],
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
            'mobile_number.regex' => 'Enter a valid Philippine mobile number, e.g. 0917 123 4567.',
            'mobile_number.unique' => 'This mobile number is already used by another organization account.',
            'documents.required' => 'Upload at least one verification document, such as a business permit or SEC/DTI registration.',
            'documents.*.mimes' => 'Documents must be PDF, JPG or PNG files.',
            'documents.*.extensions' => 'Documents must be PDF, JPG or PNG files.',
            'documents.*.max' => 'Each document must be 5 MB or smaller.',
            'logo.max' => 'The logo must be 2 MB or smaller.',
            'privacy_consent.accepted' => 'You must agree to the Privacy Notice to register.',
        ];
    }
}

<?php

namespace App\Http\Requests\Auth;

use App\Enums\AccountType;
use App\Enums\Classification;
use App\Models\Barangay;
use App\Models\User;
use App\Support\MobileNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterSeekerRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile_number' => MobileNumber::normalize($this->input('mobile_number')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'mobile_number' => ['required', 'string', 'regex:/^09\d{9}$/', MobileNumber::uniqueFor(AccountType::Seeker)],
            'barangay_id' => ['required', 'integer', Rule::exists(Barangay::class, 'id')],
            'classification' => ['required', Rule::enum(Classification::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'privacy_consent' => ['accepted'],
            'sms_consent' => ['boolean'],
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
            'mobile_number.regex' => 'Enter a valid Philippine mobile number, e.g. 0917 123 4567.',
            'mobile_number.unique' => 'This mobile number is already used by another job seeker account.',
            'barangay_id.required' => 'Select your barangay.',
            'barangay_id.exists' => 'Select a barangay from the list.',
            'privacy_consent.accepted' => 'You must agree to the Privacy Notice to create an account.',
        ];
    }
}

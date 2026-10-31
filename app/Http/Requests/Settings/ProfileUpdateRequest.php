<?php

namespace App\Http\Requests\Settings;

use App\Models\Barangay;
use App\Models\User;
use App\Support\MobileNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('mobile_number')) {
            $this->merge([
                'mobile_number' => MobileNumber::normalize($this->input('mobile_number')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Mobile number and barangay are required for seekers; staff accounts
     * may leave them empty.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isSeeker = $this->user()->isSeeker();

        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            'mobile_number' => [
                Rule::requiredIf($isSeeker),
                'nullable',
                'string',
                'regex:/^09\d{9}$/',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            'barangay_id' => [
                Rule::requiredIf($isSeeker),
                'nullable',
                'integer',
                Rule::exists(Barangay::class, 'id'),
            ],
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
            'mobile_number.unique' => 'This mobile number is already registered.',
            'barangay_id.required' => 'Select your barangay.',
        ];
    }
}

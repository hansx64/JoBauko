<?php

namespace App\Http\Requests\Seeker;

use App\Enums\Classification;
use App\Enums\EducationLevel;
use App\Enums\EducationStatus;
use App\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonalInfoRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classification' => ['required', Rule::enum(Classification::class)],
            'date_of_birth' => ['nullable', 'date', 'after:1900-01-01', 'before:-10 years'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'address_line' => ['nullable', 'string', 'max:255'],
            'highest_education' => ['nullable', Rule::enum(EducationLevel::class)],
            'current_education_status' => ['nullable', Rule::enum(EducationStatus::class)],
            'summary' => ['nullable', 'string', 'max:2000'],
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
            'date_of_birth.before' => 'You must be at least 10 years old.',
        ];
    }
}

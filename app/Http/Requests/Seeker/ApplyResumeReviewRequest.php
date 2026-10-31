<?php

namespace App\Http\Requests\Seeker;

use App\Enums\EducationLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyResumeReviewRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The same constraints as entering each item by hand.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'skills' => ['array', 'max:60'],
            'skills.*' => ['required', 'string', 'min:2', 'max:100'],

            'education' => ['array', 'max:10'],
            'education.*.level' => ['required', Rule::enum(EducationLevel::class)],
            'education.*.school' => ['required', 'string', 'max:255'],
            'education.*.course' => ['nullable', 'string', 'max:255'],
            'education.*.year_graduated' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 6)],
            'education.*.is_current' => ['boolean'],

            'experience' => ['array', 'max:15'],
            'experience.*.position' => ['required', 'string', 'max:255'],
            'experience.*.company' => ['required', 'string', 'max:255'],
            'experience.*.start_date' => ['required', 'date', 'before_or_equal:today'],
            'experience.*.end_date' => ['nullable', 'date', 'before_or_equal:today'],
            'experience.*.description' => ['nullable', 'string', 'max:2000'],

            'certifications' => ['array', 'max:20'],
            'certifications.*.name' => ['required', 'string', 'max:255'],
            'certifications.*.issuer' => ['nullable', 'string', 'max:255'],

            'trainings' => ['array', 'max:20'],
            'trainings.*.title' => ['required', 'string', 'max:255'],
            'trainings.*.provider' => ['nullable', 'string', 'max:255'],
            'trainings.*.hours' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'education.*.level' => 'education level',
            'education.*.school' => 'school',
            'experience.*.position' => 'position',
            'experience.*.company' => 'company',
            'experience.*.start_date' => 'start date',
            'certifications.*.name' => 'certification name',
            'trainings.*.title' => 'training title',
        ];
    }
}

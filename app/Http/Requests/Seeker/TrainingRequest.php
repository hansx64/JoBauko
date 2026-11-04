<?php

namespace App\Http\Requests\Seeker;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrainingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // Shown on the resume, so employers can judge the training.
            'provider' => ['required', 'string', 'max:255'],
            'completed_at' => ['required', 'date', 'before_or_equal:today'],
            'hours' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider.required' => 'Enter who conducted the training.',
            'completed_at.required' => 'Enter the date you completed the training.',
            'completed_at.before_or_equal' => 'The completion date cannot be in the future.',
            'hours.required' => 'Enter the number of hours.',
        ];
    }
}

<?php

namespace App\Http\Requests\Seeker;

use App\Enums\EducationLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EducationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(EducationLevel::class)],
            'school' => ['required', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            // Students may enter their expected graduation year.
            'year_graduated' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 6)],
            'is_current' => ['boolean'],
        ];
    }
}

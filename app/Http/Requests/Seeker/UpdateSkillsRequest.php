<?php

namespace App\Http\Requests\Seeker;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSkillsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'skills' => ['present', 'array', 'max:60'],
            'skills.*' => ['required', 'string', 'min:2', 'max:100', 'distinct:ignore_case'],
        ];
    }
}

<?php

namespace App\Http\Requests\Seeker;

use App\Enums\InterestCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInterestsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'interests' => ['present', 'array', 'max:20'],
            'interests.*.category' => ['required', Rule::enum(InterestCategory::class)],
            'interests.*.field' => ['nullable', 'string', 'max:100'],
        ];
    }
}

<?php

namespace App\Http\Requests\Seeker;

use App\Enums\CivilStatus;
use App\Models\ResumeDetail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResumeDetailsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'desired_position' => ['nullable', 'string', 'max:150'],
            'objective' => ['nullable', 'string', 'max:1000'],
            'languages' => ['array', 'max:'.ResumeDetail::MAX_LANGUAGES],
            'languages.*' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'references' => ['array', 'max:'.ResumeDetail::MAX_REFERENCES],
            'references.*.name' => ['required', 'string', 'max:120'],
            'references.*.position' => ['nullable', 'string', 'max:120'],
            'references.*.organization' => ['nullable', 'string', 'max:150'],
            'references.*.contact' => ['required', 'string', 'max:120'],
            'civil_status' => ['nullable', Rule::enum(CivilStatus::class)],
            'nationality' => ['nullable', 'string', 'max:60'],
            'show_birthdate' => ['boolean'],
            'show_sex' => ['boolean'],
            'include_certification' => ['boolean'],
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
            'languages.max' => 'List up to '.ResumeDetail::MAX_LANGUAGES.' languages.',
            'languages.*.distinct' => 'This language is already listed.',
            'references.max' => 'List up to '.ResumeDetail::MAX_REFERENCES.' character references.',
            'references.*.name.required' => "Enter the reference's name.",
            'references.*.contact.required' => 'Enter a mobile number or email so employers can reach this reference.',
        ];
    }

    /**
     * The validated details, with only the reference fields we store.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        $data = $this->validated();

        $data['languages'] = array_values(array_map('trim', $data['languages'] ?? []));
        $data['references'] = array_map(fn (array $r) => [
            'name' => $r['name'],
            'position' => $r['position'] ?? null,
            'organization' => $r['organization'] ?? null,
            'contact' => $r['contact'],
        ], array_values($data['references'] ?? []));

        return $data;
    }
}

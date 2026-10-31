<?php

namespace App\Http\Requests\Seeker;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddApplicationDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:pdf,docx,jpg,jpeg,png', 'extensions:pdf,docx,jpg,jpeg,png', 'max:'.SubmitApplicationRequest::MAX_KB],
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
            'label.required' => 'Say what this document is, e.g. "Certificate of Grades".',
            'file.mimes' => 'Upload a PDF, Word (DOCX), JPG or PNG file.',
            'file.extensions' => 'Upload a PDF, Word (DOCX), JPG or PNG file.',
            'file.max' => 'The file must not be larger than 5 MB.',
        ];
    }
}

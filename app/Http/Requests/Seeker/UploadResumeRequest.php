<?php

namespace App\Http\Requests\Seeker;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadResumeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Both the detected content type (mimes) and the file name extension
     * (extensions) must be PDF or DOCX.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resume' => ['required', 'file', 'mimes:pdf,docx', 'extensions:pdf,docx', 'max:5120'],
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
            'resume.mimes' => 'Upload a PDF or Word (DOCX) file.',
            'resume.extensions' => 'Upload a PDF or Word (DOCX) file.',
            'resume.max' => 'The resume must not be larger than 5 MB.',
        ];
    }
}

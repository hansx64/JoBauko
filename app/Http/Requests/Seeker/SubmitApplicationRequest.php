<?php

namespace App\Http\Requests\Seeker;

use App\Models\Opportunity;
use App\Models\OpportunityDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class SubmitApplicationRequest extends FormRequest
{
    public const MAX_KB = 5120;

    /**
     * Get the validation rules that apply to the request.
     *
     * Each document the opportunity asks for gets its own rule, limited to
     * the file types the organization accepts (content and extension).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'include_resume' => ['boolean'],
            'consent' => ['accepted'],
            'documents' => ['array'],
        ];

        foreach ($this->opportunity()->requiredDocuments as $document) {
            $types = self::fileTypes($document);

            $rules["documents.{$document->id}"] = [
                $document->is_required && ! $this->coveredByResume($document) ? 'required' : 'nullable',
                'file',
                'mimes:'.$types,
                'extensions:'.$types,
                'max:'.self::MAX_KB,
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'consent.accepted' => 'Please agree to share your application with the organization.',
        ];

        foreach ($this->opportunity()->requiredDocuments as $document) {
            $messages["documents.{$document->id}.required"] = "Attach your {$document->name}.";
            $messages["documents.{$document->id}.mimes"] = 'Accepted file types: '.self::fileTypes($document).'.';
            $messages["documents.{$document->id}.extensions"] = 'Accepted file types: '.self::fileTypes($document).'.';
            $messages["documents.{$document->id}.max"] = 'Each file must not be larger than 5 MB.';
        }

        return $messages;
    }

    /**
     * A resume document is satisfied by the profile resume sent with the
     * application.
     */
    private function coveredByResume(OpportunityDocument $document): bool
    {
        return $document->isResume()
            && $this->boolean('include_resume', true)
            && $this->user()?->currentResume !== null;
    }

    /**
     * Uploaded files keyed by opportunity document id.
     *
     * @return array<int, UploadedFile>
     */
    public function documentFiles(): array
    {
        $ids = $this->opportunity()->requiredDocuments->pluck('id')->all();

        return collect($this->file('documents', []))
            ->filter(fn ($file, $id) => in_array((int) $id, $ids, true))
            ->mapWithKeys(fn ($file, $id) => [(int) $id => $file])
            ->all();
    }

    /**
     * Accepted file types; "jpg" also allows the ".jpeg" spelling.
     */
    public static function fileTypes(OpportunityDocument $document): string
    {
        $types = array_filter(array_map('trim', explode(',', strtolower($document->accepted_types ?: 'pdf,docx,jpg,png'))));

        if (in_array('jpg', $types, true)) {
            $types[] = 'jpeg';
        }

        return implode(',', array_unique($types));
    }

    private function opportunity(): Opportunity
    {
        /** @var Opportunity $opportunity */
        $opportunity = $this->route('opportunity');

        return $opportunity;
    }
}

<?php

namespace App\Http\Requests\Organization;

use App\Enums\AssistanceType;
use App\Enums\Classification;
use App\Enums\EducationLevel;
use App\Enums\EmploymentType;
use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Enums\TrainingType;
use App\Enums\WorkSetup;
use App\Models\Barangay;
use App\Models\Opportunity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Drafts may be incomplete. Publishing, or editing an opportunity that is
 * already live, requires the full set of fields.
 */
class OpportunityRequest extends FormRequest
{
    public function isPublishing(): bool
    {
        return $this->input('intent') === 'publish';
    }

    private function isStrict(): bool
    {
        $opportunity = $this->route('opportunity');

        return $this->isPublishing()
            || ($opportunity instanceof Opportunity && $opportunity->status !== OpportunityStatus::Draft);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $strict = $this->isStrict();
        $requiredWhenStrict = $strict ? 'required' : 'nullable';
        $category = $this->input('category');

        return [
            'intent' => ['nullable', Rule::in(['draft', 'publish'])],
            'category' => ['required', Rule::enum(OpportunityCategory::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => [$requiredWhenStrict, 'string', 'max:10000'],
            'qualifications' => ['nullable', 'string', 'max:5000'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'application_instructions' => ['nullable', 'string', 'max:5000'],
            'min_education' => ['nullable', Rule::enum(EducationLevel::class)],
            'barangay_id' => ['nullable', 'integer', Rule::exists(Barangay::class, 'id')],
            'location_text' => ['nullable', 'string', 'max:255'],
            'is_remote' => ['boolean'],
            'application_start' => ['nullable', 'date'],
            'application_deadline' => array_filter([
                $requiredWhenStrict,
                'date',
                $this->isPublishing() ? 'after_or_equal:today' : null,
                $this->filled('application_start') ? 'after_or_equal:application_start' : null,
            ]),
            'slots' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'regex:/^[0-9+()\-\s]{7,20}$/'],

            'requirements' => ['array', 'max:30'],
            'requirements.*' => ['required', 'string', 'max:1000'],
            'documents' => ['array', 'max:15'],
            'documents.*.id' => ['nullable', 'integer'],
            'documents.*.name' => ['required', 'string', 'max:255'],
            'documents.*.is_required' => ['boolean'],
            'skills' => ['array', 'max:30'],
            'skills.*' => ['required', 'string', 'min:2', 'max:100', 'distinct:ignore_case'],

            // Job details
            'job' => ['nullable', 'array'],
            'job.employment_type' => [$category === 'job' ? $requiredWhenStrict : 'nullable', Rule::enum(EmploymentType::class)],
            'job.salary_min' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'job.salary_max' => array_filter(['nullable', 'numeric', 'min:0', 'max:10000000', $this->filled('job.salary_min') ? 'gte:job.salary_min' : null]),
            'job.work_setup' => ['nullable', Rule::enum(WorkSetup::class)],
            'job.min_experience_years' => ['nullable', 'integer', 'min:0', 'max:50'],

            // Scholarship details
            'scholarship' => ['nullable', 'array'],
            'scholarship.education_level' => [$category === 'scholarship' ? $requiredWhenStrict : 'nullable', Rule::enum(EducationLevel::class)],
            'scholarship.eligible_courses' => ['nullable', 'array', 'max:50'],
            'scholarship.eligible_courses.*' => ['required', 'string', 'max:255'],
            'scholarship.academic_requirements' => ['nullable', 'string', 'max:5000'],
            'scholarship.financial_requirements' => ['nullable', 'string', 'max:5000'],

            // Livelihood details
            'livelihood' => ['nullable', 'array'],
            'livelihood.assistance_type' => [$category === 'livelihood' ? $requiredWhenStrict : 'nullable', Rule::enum(AssistanceType::class)],
            'livelihood.target_beneficiaries' => [$category === 'livelihood' ? $requiredWhenStrict : 'nullable', 'string', 'max:3000'],
            'livelihood.assistance_provided' => [$category === 'livelihood' ? $requiredWhenStrict : 'nullable', 'string', 'max:3000'],
            'livelihood.training_requirements' => ['nullable', 'string', 'max:3000'],
            'livelihood.target_classifications' => ['nullable', 'array'],
            'livelihood.target_classifications.*' => [Rule::enum(Classification::class)],

            // Training and seminar details
            'training' => ['nullable', 'array'],
            'training.training_type' => [$category === 'training' ? $requiredWhenStrict : 'nullable', Rule::enum(TrainingType::class)],
            'training.starts_on' => ['nullable', 'date'],
            'training.ends_on' => array_filter(['nullable', 'date', $this->filled('training.starts_on') ? 'after_or_equal:training.starts_on' : null]),
            'training.schedule' => ['nullable', 'string', 'max:255'],
            'training.duration_hours' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'training.fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'training.certificate' => ['nullable', 'string', 'max:255'],
            'training.topics' => [$category === 'training' ? $requiredWhenStrict : 'nullable', 'string', 'max:3000'],
            'training.inclusions' => ['nullable', 'string', 'max:3000'],
            'training.target_participants' => ['nullable', 'string', 'max:3000'],
            'training.target_classifications' => ['nullable', 'array'],
            'training.target_classifications.*' => [Rule::enum(Classification::class)],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->isStrict() && ! $this->filled('barangay_id') && ! $this->filled('location_text') && ! $this->boolean('is_remote')) {
                    $validator->errors()->add('location_text', 'Choose a Bauko barangay, enter another location, or mark it as remote.');
                }
            },
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
            'job.employment_type' => 'employment type',
            'job.salary_min' => 'minimum salary',
            'job.salary_max' => 'maximum salary',
            'job.min_experience_years' => 'required experience',
            'scholarship.education_level' => 'educational level',
            'livelihood.assistance_type' => 'type of assistance',
            'livelihood.target_beneficiaries' => 'target beneficiaries',
            'livelihood.assistance_provided' => 'assistance provided',
            'training.training_type' => 'type of training',
            'training.starts_on' => 'start date',
            'training.ends_on' => 'end date',
            'training.duration_hours' => 'number of hours',
            'training.fee' => 'fee',
            'training.topics' => 'what participants will learn',
            'requirements.*' => 'requirement',
            'documents.*.name' => 'document name',
            'skills.*' => 'skill',
        ];
    }
}
